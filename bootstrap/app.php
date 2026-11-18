<?php

use App\Http\Middleware\ActAsDemoUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // NOTE (take-home): auth is stubbed. Every request is resolved as the
            // seeded demo user so you can build user-scoped features (e.g. saved
            // searches) against auth()->user() without wiring up a real login flow.
            // See the README ("Authentication") for the rationale.
            //
            // This must run before HandleInertiaRequests, which reads the user
            // when it builds the shared props.
            ActAsDemoUser::class,

            HandleInertiaRequests::class,
        ]);

        // There's no login page. A guest (only possible before seeding) is sent
        // to the listings page instead of a missing `login` route.
        $middleware->redirectGuestsTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
