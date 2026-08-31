# Street Listings — Project Context

A property-listings app: Laravel 13 + Inertia + Vue 3 + Tailwind. Lists
properties for sale, organised by branch, with filtering and a detail view.

## Stack

- **Laravel 13** — no separate JSON API. Controllers return
  `Inertia::render(...)` with props; API Resources define the shape of
  those props.
- **Inertia + Vue 3** — pages in `resources/js/pages/`, shared components
  in `resources/js/components/`.
- **Tailwind** for styling.
- **SQLite** locally (bundled with PHP, no separate DB server).

## Layout

app/
Enums/ PropertyType, ListingStatus
Http/
Controllers/ ListingController
Requests/ ListingIndexRequest (filter validation)
Resources/ ListingResource, BranchResource
Middleware/ ActAsDemoUser (auth stub — see below)
HandleInertiaRequests (shared props)
Models/ Branch, Listing
database/
factories/ BranchFactory, ListingFactory (with states)
migrations/ branches, listings
seeders/ DatabaseSeeder
resources/js/
pages/Listings/ Index.vue, Show.vue
components/ AppLayout, ListingCard, ListingFilters, Pagination
app.js Inertia entry point
routes/ web.php
tests/Feature/ ListingPageTest, ListingTest


## The domain

- **Branch** — a name and a region.
- **Listing** — address, price, bedrooms, bathrooms, `property_type`,
  `status` (`draft` / `live` / `under_offer` / `sold`), a branch, and a
  `listed_at` date.

## Conventions

- **Routing**: `routes/web.php`, resolves to `Inertia::render`.
- **Resources**: one per model concept (`ListingResource`,
  `BranchResource`), define the shape of data sent to the frontend. Don't
  introduce a parallel JSON/REST style without good reason.
- **Enums**: domain vocabularies (`PropertyType`, `ListingStatus`) live in
  `app/Enums/`. Prefer PHP enums over magic strings/constants.
- **Validation**: Form Requests (`app/Http/Requests/`), not inline
  controller validation — see `ListingIndexRequest`.
- **Filters/state**: query-string driven, so a search is shareable,
  bookmarkable, and survives the back button (`ListingFilters.vue` seeds
  from the `filters` prop, re-issues `router.get` on submit).

## Authentication

Auth is stubbed via `App\Http\Middleware\ActAsDemoUser` — every request
resolves as the seeded demo user (`demo@street.example`). Build
user-scoped work against `$request->user()` / `auth()->user()` as normal;
it returns the demo user. The resolver returns `null` until the database
is seeded (`php artisan migrate --seed`).

## Checks before considering work done

php artisan test # PHPUnit feature/unit tests
vendor/bin/pint --test # Code style (Laravel Pint)
vendor/bin/phpstan analyse # Static analysis (Larastan, level 5)


All three run in CI (`.github/workflows/ci.yml`) on push and PR.
