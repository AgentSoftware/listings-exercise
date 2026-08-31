<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Http\Requests\ListingIndexRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ListingResource;
use App\Models\Branch;
use App\Models\Listing;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * List live listings, with optional filters.
     */
    public function index(ListingIndexRequest $request): Response
    {
        // Reuse the same filtering logic as the main listings search (see the
        // `filter()` scope on Listing), so saved searches match the same criteria.
        $query = Listing::query()->live()->filter(
            $request->only('property_type', 'max_price', 'min_bedrooms', 'region')
        );

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->string('property_type'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->integer('max_price'));
        }

        if ($request->filled('min_bedrooms')) {
            $query->where('bedrooms', '>=', $request->integer('min_bedrooms'));
        }

        if ($request->filled('region')) {
            $region = $request->string('region');
            $query->whereHas('branch', function ($branchQuery) use ($region) {
                $branchQuery->where('region', $region);
            });
        }

        // `id` is a tiebreaker: without it, listings sharing a `listed_at` can be
        // ordered differently between page requests, which duplicates or skips
        // rows as you page through.
        $listings = $query->latest('listed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Listings/Index', [
            'listings' => ListingResource::collection($listings),
            'branches' => BranchResource::collection(Branch::query()->orderBy('name')->get()),
            'propertyTypes' => PropertyType::options(),
            'filters' => $request->only('property_type', 'max_price', 'min_bedrooms', 'region'),
        ]);
    }

    /**
     * Only live listings are public. Drafts, under-offer and sold listings are
     * not exposed here, matching the index page.
     */
    public function show(Listing $listing): Response
    {
        abort_unless($listing->status === ListingStatus::Live, 404);

        return Inertia::render('Listings/Show', [
            'listing' => new ListingResource($listing),
        ]);
    }
}
