<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Http\Requests\SavedSearchStoreRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\SavedSearchResource;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    public function index(): Response
    {
        $request = request();

        $filters = $request->only('property_type', 'max_price', 'min_bedrooms', 'region');

        $listings = Listing::query()
            ->live()
            ->filter($filters)
            ->latest('listed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('SavedSearches/Index', [
            'listings' => ListingResource::collection($listings),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
            'filters' => $filters,
            'savedSearches' => SavedSearchResource::collection(
                $request->user()->savedSearches()->latest()->get()
            ),
        ]);
    }

    public function store(SavedSearchStoreRequest $request): RedirectResponse
    {
        $request->user()->savedSearches()->create($request->validated());

        return back();
    }

    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === request()->user()->id, 403);

        $savedSearch->delete();

        return back();
    }
}
