<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $alerts = $request->user()->alerts()
            ->visible()
            ->with(['listing.branch', 'savedSearch'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        // Marked read with a query on the displayed IDs, leaving the loaded
        // models untouched, so this page still highlights what was new when it
        // loaded. The nav's unread count is a lazy shared prop resolved after
        // this runs, so it already shows the state after viewing.
        Alert::query()
            ->whereKey($alerts->getCollection()->pluck('id'))
            ->unread()
            ->update(['read_at' => now()]);

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
        ]);
    }
}
