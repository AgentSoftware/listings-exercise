<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\SavedSearchResource;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Saved searches are always reached through their owner rather than looked up
 * by id and then checked, so a query that isn't scoped to the current user
 * cannot be written by accident. That is why there is no policy here.
 */
class SavedSearchController extends Controller
{
    public function index(Request $request): Response
    {
        $savedSearches = $this->currentUser($request)
            ->savedSearches()
            ->withCount('alerts')
            ->latest('id')
            ->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
        ]);
    }

    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $this->currentUser($request)->savedSearches()->create([
            'name' => $request->input('name'),
            ...$request->criteria()->toArray(),
        ]);

        // Deliberately no backfill: this search will alert on listings that go
        // live from now on. Everything already on the market is one click away
        // on the saved-searches page, which links to these criteria in the
        // browse results.
        return back()->with('flash', "Search saved. We'll email you when something new matches.");
    }

    /**
     * Resolved through the relationship rather than by route-model binding, so
     * another user's search is a 404 by construction.
     */
    public function destroy(Request $request, string $savedSearch): RedirectResponse
    {
        $this->currentUser($request)->savedSearches()->findOrFail($savedSearch)->delete();

        return back()->with('flash', 'Search deleted.');
    }
}
