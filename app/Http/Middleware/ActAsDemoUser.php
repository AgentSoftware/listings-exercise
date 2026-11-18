<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take-home auth stub.
 *
 * A real deployment would authenticate the request (Sanctum, session, etc.).
 * To keep this exercise focused on the feature rather than on auth plumbing,
 * every request that isn't already authenticated is resolved as the seeded
 * demo user. Build user-scoped work against $request->user() / auth()->user()
 * exactly as you normally would.
 */
class ActAsDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // The user goes on the guard, not just the request's user resolver, so
        // that $request->user(), auth()->user(), Gate and the `can` middleware
        // all see the same user. ($request->user() reads the guard by default.)
        //
        // An already-authenticated user wins, which lets tests use actingAs().
        // There's no demo user until the database is seeded
        // (`php artisan migrate --seed`), and setUser() doesn't accept null, so
        // the request then stays unauthenticated, like a real guest.
        if (! Auth::hasUser() && ($demoUser = User::query()->orderBy('id')->first()) !== null) {
            Auth::setUser($demoUser);
        }

        return $next($request);
    }
}
