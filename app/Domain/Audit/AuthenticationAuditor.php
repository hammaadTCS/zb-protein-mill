<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Laravel\Fortify\Fortify;

/**
 * BR-USR-05, BR-AUD-01: every login, failed login and logout is audited.
 * Nothing about the attempt (e-mail, password) is written to the application log
 * (BR-SEC-17); a failed login for an unknown e-mail is stored without a user.
 */
final class AuthenticationAuditor
{
    public function login(Login $event): void
    {
        $this->write(AuditActions::LOGIN, $event->user);
    }

    public function failed(Failed $event): void
    {
        // Fortify's own login check sends the event without the user; find the
        // account from the e-mail tried. Only its id is stored, never the e-mail.
        $user = $event->user;
        $email = $event->credentials[Fortify::username()] ?? null;

        if ($user === null && is_string($email) && $email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        $this->write(AuditActions::LOGIN_FAILED, $user);
    }

    public function logout(Logout $event): void
    {
        $this->write(AuditActions::LOGOUT, $event->user);
    }

    private function write(string $action, mixed $user): void
    {
        $user = $user instanceof User ? $user : null;

        AuditLogger::log($action, $user, by: $user);
    }
}
