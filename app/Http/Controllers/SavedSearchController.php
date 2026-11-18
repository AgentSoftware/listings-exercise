<?php

namespace App\Http\Controllers;

use App\Enums\AlertFrequency;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    /**
     * The list is also the "view": a saved search has nothing to show beyond
     * its row, and "View results" re-runs it on the listings page.
     */
    public function index(Request $request): Response
    {
        $savedSearches = $request->user()->savedSearches()->latest()->orderByDesc('id')->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
        ]);
    }

    /**
     * Criteria are fixed once saved: new filters mean a new saved search.
     */
    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $criteria = $request->criteria();

        $request->user()->savedSearches()->create([
            ...$criteria->toAttributes(),
            'name' => $request->filled('name') ? $request->string('name')->toString() : $criteria->defaultName(),
            'alert_frequency' => $request->enum('alert_frequency', AlertFrequency::class),
        ]);

        return back()->with('message', 'Search saved');
    }

    /**
     * Ownership is checked by the route's `can` middleware (SavedSearchPolicy).
     * Alerts already raised by this search are kept: saved_search_id is set to
     * null on delete.
     */
    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        return back()->with('message', 'Saved search removed');
    }
}
