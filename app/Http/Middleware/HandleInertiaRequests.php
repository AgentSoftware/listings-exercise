<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],

            // One-off confirmations after a redirect ("Search saved."), read
            // lazily so pages that never show one don't touch the session.
            'flash' => fn () => $request->session()->get('flash'),

            // Backs the nav badge, so it is needed on every page. One indexed
            // count per request — see NOTES.md if that ever stops being cheap.
            //
            // Deliberately a top-level scalar rather than an `alerts` object: a
            // page prop of the same name silently overrides a shared one, and
            // the alerts page itself passes `alerts`.
            'unreadAlerts' => fn () => $request->user()?->alerts()->unread()->count() ?? 0,
        ];
    }
}
