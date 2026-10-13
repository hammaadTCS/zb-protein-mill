#!/usr/bin/env python3
"""PreToolUse hook for Claude Code (cloud sessions and the laptop).

Stops Claude from:
  - editing protected project files (rules, cards, tests of record, server files, tooling);
  - changing main: committing, merging, rebasing, resetting or pulling while on main, moving or
    deleting main, pushing to main, pushing or creating tags, deleting remote branches;
  - writing to GitHub other than pushing its own task branch (gh api writes, gh pr merge,
    gh release, gh workflow ...);
  - approving protected-file changes itself (check-all.sh --spec-change).

Claude may push its own task branch; the human opens or approves the pull request and merges it.
Exit code 2 blocks the tool call and shows the message to Claude.
Backstops if this hook cannot run: the protected-files check on every pull request, the main guard
on every push to main (scripts/check-main-push.sh), and the release workflow, which refuses a
commit whose CI run on main is not green.
"""
import subprocess
import json
import os
import re
import shlex
import sys

PROTECTED_ANY = re.compile(
    r"^(CLAUDE\.md|START-HERE\.md|\.env\.production\.example"
    r"|README\.md"
    r"|docs/(README|spec|requirements|requirements-audit|conventions|glossary|brand|prompts|traceability"
    r"|architecture|frontend|backend|security|secrets|data-handling|environments|stack-baseline)\.md"
    r"|docs/(cards|acceptance|templates|adr|operations|client)/"
    r"|brand/|infra/"
    r"|scripts/"
    r"|\.claude/|\.github/)"
)
PROTECTED_EXISTING = re.compile(r"^(tests/Acceptance/|tests/Arch/)")

MESSAGE = (
    "BLOCKED: {path} is a protected project file. Only the human changes it. "
    "Do not try another way to change it. If you believe it must change, stop and "
    "write the exact proposed change in your final message."
)


def rel(path: str, root: str) -> str:
    path = path.replace("\\", "/")
    root = root.replace("\\", "/").rstrip("/")
    if not os.path.isabs(path):
        path = os.path.normpath(os.path.join(os.getcwd(), path)).replace("\\", "/")
    if path.startswith(root + "/"):
        return path[len(root) + 1:]
    return path


def is_protected(path: str, root: str, for_write: bool = True) -> bool:
    r = rel(path, root).rstrip("/")
    if r in ("", "."):
        return True  # the whole project
    if PROTECTED_ANY.match(r) or PROTECTED_ANY.match(r + "/"):
        return True
    if PROTECTED_EXISTING.match(r) and os.path.exists(os.path.join(root, r)):
        return True
    # A folder that contains protected files (for example "rm -rf docs").
    full = os.path.join(root, r)
    if not os.path.isabs(r) and os.path.isdir(full):
        for dirpath, dirnames, filenames in os.walk(full):
            dirnames[:] = [d for d in dirnames if d not in ("vendor", "node_modules", ".git")]
            for f in filenames:
                rr = rel(os.path.join(dirpath, f), root)
                if PROTECTED_ANY.match(rr) or PROTECTED_EXISTING.match(rr):
                    return True
    return False


PUSH_MESSAGE = (
    "BLOCKED: {why}. Claude works only on its own task branch and may push only that branch. "
    "Changing main, merging pull requests, tags, releases and other writes to GitHub are done by "
    "the human only (CLAUDE.md, Git). Do not try another way."
)
ON_MAIN_BLOCKED = ("commit", "merge", "rebase", "reset", "cherry-pick", "revert", "pull", "am")
MAIN_BRANCHES = {"main", "master"}
SEPARATORS = {"&&", "||", ";", "|", "&"}


def split_segments(command: str):
    tokens = shlex.split(command, posix=True)
    segs, cur = [], []
    for t in tokens:
        if t in SEPARATORS:
            segs.append(cur)
            cur = []
        else:
            cur.append(t)
    segs.append(cur)
    return [s for s in segs if s]


def git_parts(seg):
    """For a 'git ...' segment return (subcommand, args, directory given with -C)."""
    i, cdir = 1, None
    while i < len(seg):
        t = seg[i]
        if t in ("-C", "-c", "--git-dir", "--work-tree", "--namespace", "--exec-path"):
            if t == "-C" and i + 1 < len(seg):
                cdir = seg[i + 1]
            i += 2
            continue
        if t.startswith("-"):
            i += 1
            continue
        return t, seg[i + 1:], cdir
    return None, [], cdir


def current_branch(directory: str):
    try:
        out = subprocess.run(["git", "-C", directory, "rev-parse", "--abbrev-ref", "HEAD"],
                             capture_output=True, text=True, timeout=10)
    except Exception:
        return None
    return out.stdout.strip() if out.returncode == 0 else None


def ref_exists(directory: str, ref: str) -> bool:
    try:
        out = subprocess.run(["git", "-C", directory, "show-ref", "--verify", "--quiet", ref],
                             capture_output=True, timeout=10)
    except Exception:
        return False
    return out.returncode == 0


def is_tag(directory: str, name: str) -> bool:
    return ref_exists(directory, "refs/tags/" + name)


def git_push_problem(args, directory: str, assumed_branch=None):
    """Allow pushing a task branch; refuse main, tags, --all/--mirror and deletions."""
    positional, i = [], 0
    while i < len(args):
        a = args[i]
        if a in ("--all", "--mirror", "--tags", "--follow-tags", "--delete", "-d", "--prune"):
            return "git push " + a + " is not allowed"
        if a in ("-o", "--push-option", "--repo", "--receive-pack", "--exec"):
            i += 2
            continue
        if not a.startswith("-"):
            positional.append(a)
        i += 1
    refspecs = positional[1:]
    branch = assumed_branch or current_branch(directory)
    if not refspecs:
        if branch is None:
            return "could not tell which branch this push would update"
        if branch in MAIN_BRANCHES:
            return "this would push " + branch
        return None
    for spec in refspecs:
        r = spec.lstrip("+")
        if r.startswith(":"):
            return "deleting a remote branch or tag is not allowed"
        dest = r.split(":", 1)[1] if ":" in r else r
        if dest == "HEAD":
            dest = branch or "HEAD"
        if dest.startswith("refs/tags/") or (":" not in r and is_tag(directory, dest)):
            return "pushing a tag is not allowed (releases are made by the human)"
        if dest.startswith("refs/heads/"):
            dest = dest[len("refs/heads/"):]
        if dest in MAIN_BRANCHES:
            return "this would push " + dest
    return None


def gh_problem(seg):
    args = seg[1:]
    if not args:
        return None
    sub = args[0]
    if sub == "api":
        method, writes, i = None, False, 1
        while i < len(args):
            a = args[i]
            if a in ("-X", "--method") and i + 1 < len(args):
                method = args[i + 1].upper()
                i += 2
                continue
            if a.startswith("--method="):
                method = a.split("=", 1)[1].upper()
            elif a.startswith("-X") and len(a) > 2:
                method = a[2:].upper()
            elif a in ("-f", "-F", "--field", "--raw-field", "--input") or \
                    a.startswith(("--field=", "--raw-field=", "--input=")):
                writes = True
            i += 1
        if (method or ("POST" if writes else "GET")) != "GET":
            return "gh api may only read (GET)"
        return None
    if sub in ("release", "workflow", "secret", "variable", "ruleset", "repo"):
        return "gh " + sub + " is not allowed"
    if sub == "pr" and len(args) > 1 and args[1] in ("merge", "close", "reopen", "edit", "ready"):
        return "gh pr " + args[1] + " is not allowed"
    return None


def strip_prefixes(seg):
    """Drop wrappers such as 'sudo', 'env A=1', 'timeout 60', 'A=1' in front of a command."""
    seg = list(seg)
    while seg:
        head = os.path.basename(seg[0])
        if "=" in seg[0] and not seg[0].startswith("-") and head == seg[0]:
            seg.pop(0)
        elif head in ("sudo", "command", "nohup", "time", "exec", "builtin", "nice"):
            seg.pop(0)
            while seg and seg[0].startswith("-"):
                seg.pop(0)
        elif head == "env":
            seg.pop(0)
            while seg and (seg[0].startswith("-") or "=" in seg[0]):
                seg.pop(0)
        elif head == "timeout":
            seg.pop(0)
            while seg and seg[0].startswith("-"):
                seg.pop(0)
            if seg:
                seg.pop(0)  # the duration
        else:
            break
    return seg


def command_problem(command: str, root: str):
    """Return why a shell command must be blocked for git/GitHub reasons, or None."""
    try:
        segs = split_segments(command)
    except ValueError:
        return None
    directory = root
    assumed_branch = None  # set by "git checkout X" / "git switch X" earlier in the same command
    for seg in segs:
        seg = strip_prefixes(seg)
        if not seg:
            continue
        name = os.path.basename(seg[0])
        if any(os.path.basename(t) == "check-all.sh" for t in seg) and "--spec-change" in seg:
            return "approving protected-file changes (--spec-change) is for the human only"
        if name in ("bash", "sh", "zsh", "dash") and "-c" in seg:
            k = seg.index("-c")
            if k + 1 < len(seg):
                why = command_problem(seg[k + 1], directory)
                if why:
                    return why
            continue
        if name == "eval":
            why = command_problem(" ".join(seg[1:]), directory)
            if why:
                return why
            continue
        if name == "cd" and len(seg) > 1:
            directory = seg[1] if os.path.isabs(seg[1]) else os.path.join(directory, seg[1])
            continue
        if name == "git":
            sub, args, cdir = git_parts(seg)
            if sub in ("checkout", "switch"):
                created, names = None, []
                for k, a in enumerate(args):
                    if a in ("-b", "-B", "-c", "-C") and k + 1 < len(args):
                        created = args[k + 1]
                        break
                    if not a.startswith("-"):
                        names.append(a)
                if created and created in MAIN_BRANCHES and \
                        any(a in ("-B", "-C") for a in args):
                    return "resetting main with git " + sub + " is not allowed"
                if created:
                    assumed_branch = created
                elif len(names) == 1 and "--" not in args and \
                        ref_exists(directory, "refs/heads/" + names[0]):
                    assumed_branch = names[0]
                continue
            branch_here = assumed_branch or current_branch(
                (cdir if cdir and os.path.isabs(cdir) else os.path.join(directory, cdir)) if cdir else directory)
            if sub in ON_MAIN_BLOCKED and branch_here in MAIN_BRANCHES:
                return "git " + sub + " on " + branch_here + " is not allowed: create your task branch first (git checkout -b task/<id>-<name>)"
            if sub == "branch":
                low = [a for a in args]
                moving = any(a in ("-f", "--force", "-D", "-d", "--delete", "-m", "-M", "-c", "-C") for a in low)
                if moving and any(a in MAIN_BRANCHES for a in low):
                    return "moving, renaming or deleting " + "/".join(a for a in low if a in MAIN_BRANCHES) + " is not allowed"
            if sub == "update-ref" and any(a.endswith("/main") or a.endswith("/master") or a in MAIN_BRANCHES for a in args):
                return "git update-ref on main is not allowed"
            if sub == "tag":
                creating = [a for a in args if not a.startswith("-")]
                listing = any(a in ("-l", "--list", "-v", "--verify") for a in args) or not args
                if creating and not listing:
                    return "creating or deleting tags is not allowed (releases are made by the human)"
            if sub == "push":
                d = directory
                if cdir:
                    d = cdir if os.path.isabs(cdir) else os.path.join(directory, cdir)
                why = git_push_problem(args, d, assumed_branch)
                if why:
                    return why
        elif name == "gh":
            why = gh_problem(seg)
            if why:
                return why
    return None


def bash_targets(command: str):
    """Return file paths a shell command would modify (best effort)."""
    targets = []
    try:
        tokens = shlex.split(command, posix=True)
    except ValueError:
        return [command]  # unparseable: let the substring check below decide
    cmd = []
    seps = {"&&", "||", ";", "|"}
    segments = []
    for t in tokens:
        if t in seps:
            segments.append(cmd)
            cmd = []
        else:
            cmd.append(t)
    segments.append(cmd)
    for seg in segments:
        if not seg:
            continue
        for i, t in enumerate(seg):
            if t in (">", ">>", "1>", "2>") and i + 1 < len(seg):
                targets.append(seg[i + 1])
            elif t.startswith(">") and len(t) > 1:
                targets.append(t.lstrip(">"))
        name = os.path.basename(seg[0])
        args = [a for a in seg[1:] if not a.startswith("-")]
        if name in ("rm", "rmdir", "unlink", "truncate", "chmod", "chown", "tee", "touch"):
            targets += args
        elif name in ("mv",):
            targets += args
        elif name in ("cp", "ln", "install", "rsync") and args:
            targets.append(args[-1])
        elif name in ("sed", "perl") and any(a.startswith("-i") for a in seg[1:]):
            targets += [a for a in args if "/" in a or "." in a]
        elif name == "git" and len(seg) > 1 and seg[1] in ("rm", "mv", "restore", "checkout"):
            targets += [a for a in seg[2:] if not a.startswith("-")]
    return targets


def main() -> int:
    try:
        data = json.load(sys.stdin)
    except Exception:
        return 0
    root = os.environ.get("CLAUDE_PROJECT_DIR") or data.get("cwd") or os.getcwd()
    tool = data.get("tool_name", "")
    tin = data.get("tool_input", {}) or {}

    if tool in ("Edit", "Write", "MultiEdit", "NotebookEdit"):
        path = tin.get("file_path") or tin.get("notebook_path") or ""
        if path and is_protected(path, root):
            print(MESSAGE.format(path=rel(path, root)), file=sys.stderr)
            return 2
        return 0

    if tool == "Bash":
        command = tin.get("command", "") or ""
        why = command_problem(command, root)
        if why:
            print(PUSH_MESSAGE.format(why=why), file=sys.stderr)
            return 2
        for target in bash_targets(command):
            if target and is_protected(target, root):
                print(MESSAGE.format(path=rel(target, root)), file=sys.stderr)
                return 2
        return 0

    return 0


if __name__ == "__main__":
    sys.exit(main())
