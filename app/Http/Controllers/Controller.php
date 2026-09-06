<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The user this request belongs to.
     *
     * Auth is stubbed (see the README): the resolver returns null until the
     * database is seeded, which is the same shape as a genuinely
     * unauthenticated request. User-scoped pages should refuse that rather
     * than fail on a null — and returning a non-nullable User keeps every
     * caller honest about scoping its queries.
     */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_if($user === null, 403, 'This page needs a signed-in user.');

        return $user;
    }
}
