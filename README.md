# Street Listings

A small property-listings application: a Laravel 13 API with a Vue 3 front end. It lists properties for sale, organised by branch, with filtering and a detail view.

This repository is the starting point for a technical exercise. **Your task is described in [`TASK.md`](TASK.md).** This README covers what's here and how to run it.

Saved-search alerts have been built on top — the decisions and trade-offs are in [`NOTES.md`](NOTES.md).

## Requirements

- PHP 8.4
- Composer
- Node 20+ and npm
- SQLite (bundled with PHP — no separate database server needed)

## Getting started

```bash
# 1. Install dependencies
#    composer install also creates .env from .env.example (if missing),
#    creates database/database.sqlite, and sets APP_KEY when it's blank.
composer install
npm install

# 2. Database — migrate and seed
php artisan migrate --seed

# 3. Run — in two terminals, or use the combined dev script
php artisan serve      # API + app at http://127.0.0.1:8000
npm run dev            # Vite dev server for the front end
```

Then open <http://127.0.0.1:8000>.

## Running the checks

```bash
php artisan test              # PHPUnit feature/unit tests
vendor/bin/pint --test        # Code style (Laravel Pint)
vendor/bin/phpstan analyse    # Static analysis (Larastan, level 5)
```

All three run in CI (`.github/workflows/ci.yml`) on push and pull request, and all pass on a fresh clone.

## How it's laid out

```
app/
  Alerts/                GenerateAlerts (the nightly matching run)
  Console/Commands/      DispatchAlerts (alerts:dispatch)
  Enums/                 PropertyType, ListingStatus
  Http/
    Controllers/         ListingController, SavedSearchController, AlertController
    Requests/            ListingIndexRequest, StoreSavedSearchRequest
    Resources/           ListingResource, BranchResource, SavedSearchResource, AlertResource
    Middleware/          ActAsDemoUser (auth stub — see below)
                         HandleInertiaRequests (shared props)
  Models/                Branch, Listing, SavedSearch, Alert
  Notifications/         NewListingMatches (the digest)
  Search/                ListingCriteria (one definition of "matches")
database/
  factories/             BranchFactory, ListingFactory (with states),
                         SavedSearchFactory, AlertFactory
  migrations/            branches, listings, saved_searches, alerts
  seeders/               DatabaseSeeder
resources/js/
  pages/Listings/        Index.vue, Show.vue
  pages/SavedSearches/   Index.vue
  pages/Alerts/          Index.vue
  components/            AppLayout, ListingCard, ListingFilters, Pagination
  app.js                 Inertia entry point
routes/                  web.php, console.php (the daily schedule)
tests/Feature/           ListingPageTest, ListingTest, SavedSearchTest,
                         SavedSearchPageTest, AlertPageTest,
                         GenerateAlertsTest, DispatchAlertsCommandTest
tests/Unit/              ListingCriteriaTest
```

### The domain

- **Branch** — a name and a region.
- **Listing** — address, price, bedrooms, bathrooms, `property_type`, `status` (`draft` / `live` / `under_offer` / `sold`), a branch, and a `listed_at` date.
- **Saved search** — a user's standing criteria (`property_type`, `max_price`, `min_bedrooms`, `region`), any of which may be null meaning "any".
- **Alert** — a record that a listing matched one of a user's saved searches. One per user per listing, enforced by a unique index.

### The stack

Laravel + **Inertia** + Vue 3 + Tailwind — the same shape as our internal apps. There is no separate JSON API: controllers return `Inertia::render(...)` with props, and API Resources (`ListingResource`, `BranchResource`) define the shape those props take.

| Method | Path                 | Page              | Notes                                                      |
| ------ | -------------------- | ----------------- | ---------------------------------------------------------- |
| GET    | `/`                  | `Listings/Index`  | Live listings, paginated. Filters: `property_type`, `max_price`, `min_bedrooms`, `region`, `per_page`. |
| GET    | `/listings/{listing}`| `Listings/Show`   | A single live listing. Non-live listings 404.              |
| GET    | `/saved-searches`    | `SavedSearches/Index` | The current user's saved searches.                     |
| POST   | `/saved-searches`    | —                 | Saves a search. Refuses one with no criteria.              |
| DELETE | `/saved-searches/{savedSearch}` | —      | Deletes one of the current user's searches.                |
| GET    | `/alerts`            | `Alerts/Index`    | The current user's alerts, newest first, paginated.        |
| POST   | `/alerts/read`       | —                 | Marks all of the current user's alerts read.               |

Filters live in the query string, so a search is shareable, bookmarkable and survives the back button. `pages/Listings/Index.vue` seeds its form from the `filters` prop and re-issues a `router.get` on submit.

### Authentication

Auth is **stubbed**. A real deployment would authenticate requests properly; to keep this exercise focused on the feature rather than on auth plumbing, every request is resolved as the seeded demo user (`demo@street.example`) via `App\Http\Middleware\ActAsDemoUser`, and shared to the front end as the `auth.user` prop. Build any user-scoped work against `$request->user()` / `auth()->user()` as you normally would — it will return the demo user.

Note the resolver returns `null` until the database is seeded, so run `php artisan migrate --seed` before you start. User-scoped pages (`/saved-searches`, `/alerts`) return 403 until then, which is the same shape as an unauthenticated request.

### Saved-search alerts

A buyer saves a search from the browse page ("Save this search") or from `/saved-searches`. Alerts are **not** generated the moment a listing goes live — they come from a command that runs once a day, so a buyer gets one digest rather than a message per property. See [`NOTES.md`](NOTES.md) for why.

```bash
php artisan alerts:dispatch
```

The command is idempotent: it selects listings that have no alert row yet rather than "listings added today", so a missed day is caught up by the next run and running it by hand is always safe. It is scheduled for 18:00 daily in `routes/console.php`.

Matching is a single `INSERT ... SELECT` — one statement for the whole run, whatever the volume — and delivery chunks buyers, marking `alerts.notified_at` as it goes so a run that dies part way through resumes rather than re-sending. See [`NOTES.md`](NOTES.md) for the numbers.

To see the whole thing end to end on a fresh database:

```bash
php artisan migrate:fresh --seed

# Save a search at http://127.0.0.1:8000/saved-searches, then publish a listing
# that matches it — seeded listings all predate your search, so nothing matches
# until something new goes live:
php artisan tinker
>>> $l = App\Models\Listing::where('status', 'draft')->first();
>>> $l->update(['status' => 'live', 'listed_at' => now()]);

php artisan alerts:dispatch     # creates the alerts, queues one digest per buyer
php artisan queue:work --once   # delivers it (or run `composer dev`, which
                                # keeps a worker running)
```

The alert appears at `/alerts`. `NewListingMatches` is a queued notification, so the command reports digests as *queued* — with the default `MAIL_MAILER=log` the worker renders the digest into `storage/logs/laravel.log`, where you can read what the buyer would have received. No real email is sent and no queue worker is required for the alert records themselves.

---

Your task: **[`TASK.md`](TASK.md)**.
