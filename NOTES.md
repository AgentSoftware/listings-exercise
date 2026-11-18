# Notes: saved-search alerts

## Key decisions and trade-offs

- **Alerts and emails are separate.** An *alert* is a stored match record, created whenever a listing matches a search with alerts on. An *email* (`NewListingsAlert`) is an optional bundle sent according to the search's `alert_frequency`. `SendPendingAlerts` is the only sending path, used by both instant and digest sends.
- **One alert per user per listing** (unique `user_id, listing_id`). When several searches match, the alert goes to the one with the **most frequent** alert frequency: matching runs the frequencies from most to least frequent and uses `insertOrIgnore`, so the first match wins and retrying a job is safe. A listing that comes back on the market doesn't alert again while its alert still exists (see retention). This is a deliberate anti-spam choice.
- `Never` **means a bookmark.** These searches are re-run from "View results" and never produce alerts, so an empty alerts list for them is expected, not a bug. Matching excludes them up front, so at scale they never take part in matching.
- **No backfill.** Alerts come from the `ListingWentLive` event, so only listings that go live *after* a search is saved create alerts. Anything already live is in the "View results" list. Backfilling would turn every save into a burst of alerts.
- **Criteria are stored as columns,** not JSON or a filters table. The reverse match (which searches does this listing satisfy?) is then one indexed SQL query (`SavedSearch::scopeMatching`), with null meaning "any".
- **Change to an existing pattern:** the filter logic moved out of `ListingController` into the `ListingCriteria` value object. It holds the rules, the forward match, the query string and `describe()`, the only place a summary is built. `ListingIndexRequest` and the new store request share its validation rules. A parity test checks that the forward and reverse matches agree.
- **The auth stub was extended.** `ActAsDemoUser` used to set only the request's user resolver, so `auth()->user()` and policies saw a guest. It now sets the user on the guard when nobody is authenticated and a demo user exists, so `$request->user()`, `auth()->user()` and `can` middleware all agree. `actingAs()` still takes precedence. The trade-off: one user lookup runs on every request that isn't already authenticated.
- **Criteria are fixed once saved;** new filters mean a new search, as on Rightmove and Zoopla. The list page acts as the "view" page, so there's no show page.
- **Instant debounce.** An instant match queues `SendPendingAlerts` about 10 minutes later. The job is unique per user, so a burst of listings becomes one email. It uses `ShouldBeUniqueUntilProcessing`, which releases the lock once the job starts, so an alert that arrives mid-send queues a fresh job instead of waiting for the next sweep. `WithoutOverlapping` stops two sends running at once for the same user. Alerts are marked as notified *before* sending, so delivery is at most once. The notification is itself queued with retries, so only a notification that fails permanently loses an email, and the alert still shows in the app.
- `NewListingsAlert` **takes alerts, not listings** (a departure from the brief). Each alert carries its listing and the search it matched, and the email groups listings by search. It's still one class for one listing or many.
- **Defaults:** `Never` in the database, so nothing created without an explicit choice sends email. `Instantly` is pre-selected in the save modal, because someone saving a search there is usually asking to be told.
- **The save button follows the submitted search (the URL),** which is the single source of truth for a search. Edits not yet submitted don't change it, and the modal's summary shows exactly what will be saved.
- **"✓ Saved" deletes without confirmation:** re-saving is one click, and existing alerts survive because `saved_search_id` is set to null on delete.
- **Only live listings' alerts are shown or emailed** (`Alert::scopeVisible`, shared by the alerts page, the unread count and delivery). This follows the existing rule that non-live listings aren't exposed. Whether to show an "under offer" or "sold" status instead is an open product question.
- **The flash message is centred, non-blocking and fades on its own.** A corner toast is the more common alternative.

## Demo scenario (`php artisan migrate:fresh --seed`)

The demo user has 5 saved searches, each in a different region:

| Search | Alerts | Current | Old |
|---|---|---|---|
| Flats in Manchester | Instantly | 1, emailed | none |
| Leeds · 2+ beds | Daily (due) | 3: 1 read, 2 pending | 2 unread, 100 days old |
| Liverpool · up to £600,000 | Every 3 days (due) | 7: 3 read, 1 unread, 3 pending | 2 read, 40 days old |
| Sheffield | Every 7 days (not due) | 10: 4 read, 2 unread, 4 pending | 1 unread, 45 days old |
| Bristol · up to £250,000 | Never | none | none |

- **Saved searches page:** 5 rows, with frequency badges on four and "Alerts off" on Bristol.
- **Alerts page:** 26 alerts (16 unread) over 2 pages, newest first. Viewing a page marks its alerts as read.
- `php artisan alerts:send-digests` with a worker running (`should be running now by default when running composer.dev`) **:** sends **one** email to `storage/logs` with 5 listings, under two headings: Daily's 2 pending and Every 3 days' 3 pending. Every 7 days isn't due yet, and the Instantly alert was already emailed. A second run sends nothing.
- `php artisan model:prune --pretend`**:** reports **4** alerts (the 100-day unread ones and the 40-day read ones). The 45-day unread alert is kept.

The seeder turns off model events for listings, so seeding doesn't queue hundreds of matching jobs. It creates the demo alerts directly instead, choosing each one's listing with that search's own `ListingCriteria`. `DatabaseSeederTest` checks these numbers.

## Deliberately left out

- Editing a saved search's name or frequency.
- A pivot table recording every search a listing matched.
- Radius search (the app has no geodata).
- Sending real email and running the queue: the log mailer and the `database` queue are configured, but no worker is operated.
- JS component tests: the repo has no JS test runner, so the UI is covered by server-side feature tests plus manual checks.

## With more time

- Automated component tests (Vitest + Vue Test Utils) for the save modal, the Saved/Delete button and the flash message, which are built but only checked by hand for now.
- Editing name and frequency, and the matched-search pivot.
- Aggregating alert analytics before pruning, if product wants long-term metrics.
- **Criteria:** `min_price` (a price range), `max_bedrooms`, `min_bathrooms`, multiple property types or regions, and postcode district (from the existing `postcode` column). Radius search and features like a garden or parking need data the app doesn't have yet.

## Where it wouldn't hold up yet

- **Fan-out:** one listing matching a huge number of searches is one job writing many rows. It streams in chunks of 500, but at real scale it should split into a job per chunk.
- **Queues:** `matching` and `notifications` should run on separate worker pools (Redis with Horizon, or SQS), and mail should be rate-limited with the `RateLimited` job middleware.
- **Matching:** shard it by region, and eventually move to a percolator-style reverse search (Elasticsearch/OpenSearch) once criteria become richer than four columns.
- **Retention:** Alerts are one small row per user per listing. Growth is linear and bounded by a 90-day prune; partition by month if volume demands it.
