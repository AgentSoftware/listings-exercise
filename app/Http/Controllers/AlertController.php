<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Like saved searches, alerts are only ever reached through their owner.
 */
class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $alerts = $this->buyer($request)
            ->alerts()
            ->with(['listing.branch', 'savedSearch'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
        ]);
    }

    /**
     * Marking read is an explicit action rather than a side effect of loading
     * the page, so the unread badge behaves the way the buyer expects when they
     * page back and forth.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $this->buyer($request)->alerts()->unread()->update(['read_at' => now()]);

        return back()->with('flash', 'Alerts marked as read.');
    }
}
