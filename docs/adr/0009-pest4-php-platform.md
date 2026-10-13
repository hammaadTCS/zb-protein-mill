# ADR-0009: Pest 4, a PHP 8.3 platform lock, tests on PHP 8.3 and 8.5

**Status:** Accepted (11 October 2026).

**Context.** Claude Code cloud sessions and the development laptop (Linux Mint 22, Ubuntu 24.04 base) run PHP 8.3; the servers run PHP 8.5. Pest 5 needs PHP 8.4 and PHPUnit 13; the kit pins PHPUnit 12. If `composer.lock` is ever resolved on PHP 8.5, it can pick packages that will not install on 8.3.

**Decision.** `composer config platform.php 8.3.6` in T0.1; `pestphp/pest:^4.7` and `pestphp/pest-plugin-laravel:^4.1`. CI runs the full suite on PHP 8.3 and 8.5 against PostgreSQL 18.

**Consequences.** One lock file works everywhere. Moving to Pest 5 later means raising the platform to 8.4+, which requires cloud sessions and the laptop on PHP 8.4+: check both before: check before changing.
