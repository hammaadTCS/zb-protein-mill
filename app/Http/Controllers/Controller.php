<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    // conventions §9: every action calls $this->authorize(...).
    use AuthorizesRequests;

    /** The logged-in user, who is passed to every Action (conventions §2). */
    protected function actor(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
