# Trials Scoring

Prototype Laravel app for motorcycle trials scoring. An existing Expo mobile app pushes section scores into the API; a Livewire dashboard shows results, with a live activity view fed by websockets (Laravel Reverb). It doubles as a **self-service demo**: anyone can start a simulated event from the home screen and watch it run live over a chosen timespan.

## Concepts

- **Event** — any number of events run concurrently; every URL and API call is scoped to one (`/events/{id}`). Each event broadcasts on its own channel.
- **Rider class** — defines how many laps and how many sections a class rides (e.g. Trial 1: 2 laps × 15 sections, Sub Junior: 2 laps × 8). Classes ride sections 1..N.
- **Section claim codes** — every section has a short unique code (e.g. `CZ6`, no ambiguous characters). The organiser hands codes to observers; punching a code into the app proves the observer is the official scorer for that section.
- **Official vs self scores** — scores submitted with a valid claim token are `official` and count toward results. Anyone can also self-score (no token); those are stored and shown, but excluded from all totals.
- **Idempotency** — every score submission carries a unique idempotency key. Retries and double-taps return the originally stored score (`replayed: true`) instead of creating duplicates, so a device can safely resubmit after flaky reception.

## Local development

Requires PHP 8.3+, Postgres, Node. With [Herd](https://herd.laravel.com) the site is served at `http://trials-scoring.test`.

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # first time only
php artisan migrate:fresh --seed                    # demo event + riders + scores
npm run build                                       # or: npm run dev
php artisan reverb:start                            # websockets (or: composer run dev for everything)
php artisan test                                    # Pest suite
```

The `.env` expects Postgres on `127.0.0.1:5432` (databases `trials_scoring` and `trials_scoring_test`).

## Dashboard

| Route | View |
|---|---|
| `/` | **Home** — list of running/finished events, and a "Start event" form (optional name + timespan selector) that spins up a full simulated trial. |
| `/events/{id}` | **Overview (live)** — stat widgets, countdown to riding end / cards-in deadline (set via "Set times", interpreted in `APP_DISPLAY_TIMEZONE`), a lap-progress board (each rider a pill that fills as they complete sections, hopping Lap 1 → Lap 2 → Finished), and a compact recent-scores feed. Every widget updates the moment a score arrives over websockets. |
| `/events/{id}/standings` | Positions per class — lowest points wins, ties split on cleans. Live-updating. |
| `/events/{id}/sections` | Organiser view — claim code per section, observers, per-minute activity bars for the last 20 minutes, score counts. |
| `/events/{id}/riders` | Searchable rider list with lap × section scorecards (official scores only). |

## How the self-running simulation works (no shell required)

Pressing **Start event** creates the event (60 riders across five classes, observers claiming all 15 sections) and pre-generates the *entire* event's scores into a `staged_scores` table, each row with a `due_at` spread across the chosen timespan — staggered starts, sections ridden in order, per-section difficulty skewing the points.

Nothing runs in the background. An invisible `simulation-ticker` Livewire component on every event page polls every 2 seconds; each poll releases the staged scores whose time has come — creating real `scores` rows and broadcasting `ScoreRecorded` over Reverb (a cache lock stops concurrent viewers double-releasing). Any viewer's browser drives the event forward; if nobody is watching, the event simply catches up the next time someone opens it. This is what makes it deployable on Laravel Cloud with hibernation and no long-running processes.

Locally you can also run one from the terminal (same code path):

```bash
php artisan trial:simulate --minutes=10 --riders=60 --name="Kick-off Trial"
```

Demo events self-delete a day after their cards-in deadline.

## API

Base URL: `/api/v1/events/{event}`. All endpoints are JSON; send `Accept: application/json`.

### `GET /api/v1/events/{event}`
The event, its classes (laps + section counts) and section numbers. No claim codes here — those are organiser-only.

### `POST /observer/claims`
Observer claims a section using the code the organiser gave them.

```json
{ "code": "CZ6", "device_id": "device-uuid", "observer_name": "Karen Mills" }
```

Returns `201` with a `claim_token`. Send it as a bearer token on score submissions to make them official. Codes are case-insensitive; an unknown code returns `422`. Multiple observers may claim the same section.

### `POST /scores`
Headers: `Idempotency-Key: <unique-per-tap>` (or an `idempotency_key` body field), and optionally `Authorization: Bearer <claim_token>`.

```json
{
  "rider_number": 14,
  "lap": 2,
  "points": 3,
  "device_id": "device-uuid",
  "recorded_at": "2026-07-04T12:58:00+10:00",
  "section_number": 6
}
```

- `points` must be one of `0, 1, 2, 3, 5`.
- With a valid claim token the section comes from the claim (`section_number` ignored) and the score is `official`. Without one, `section_number` is required and the score is `self`.
- `lap` and section are validated against the rider's class (laps ridden, sections 1..N).
- `201` on create; replaying a key returns `200` with `replayed: true` and the original score. An invalid token returns `401`.
- Every accepted score broadcasts `ScoreRecorded` on public channel `event.{id}`.

### `GET /riders/{number}/progress?section={n}`
Primes an observer before they score: which lap of how many they are recording for this rider at their section, and how many more visits to expect.

```json
{
  "rider": { "number": 14, "name": "Ruby Nash", "class": "Trial 2" },
  "laps_total": 2,
  "section": {
    "number": 6,
    "laps_scored": 1,
    "current_lap": 2,
    "remaining_visits": 1,
    "complete": false,
    "message": "Recording lap 2 of 2 for rider 14 — you will see them no more times after this."
  },
  "laps": [ { "lap": 1, "sections_scored": 12, "sections_total": 15 } ]
}
```

### `GET /riders/{number}`
Full scorecard: per-lap per-section points grid, lap totals, overall points, cleans. Official scores only.

## Websockets

Reverb runs locally on `:8080`. The dashboard connects via Laravel Echo (config in `resources/js/echo.js`). Broadcasts use `ShouldBroadcastNow`, so no queue worker is needed for realtime updates, and a Reverb outage never fails a score write (broadcast errors are swallowed and reported).

Channel: `event.{eventId}` · Event: `App\Events\ScoreRecorded` — payload includes rider (number/name/class), section, lap, laps_total, points, status, observer and timestamps.

## Deploying to Laravel Cloud

The stack was chosen so everything can hibernate when idle — no queue workers, no scheduler, no shell needed at runtime (the simulation is driven by viewers' browsers):

1. Create the app on Laravel Cloud, attach a **serverless Postgres** database (auto-injects the `DB_*` env vars) and enable hibernation.
2. Enable **Reverb** for the environment (Cloud provisions and manages the websocket server and sets the `REVERB_*`/`VITE_REVERB_*` vars).
3. Env: `BROADCAST_CONNECTION=reverb`, `APP_ENV=production`, optionally `APP_DISPLAY_TIMEZONE` (defaults to Australia/Sydney).
4. Deploy commands: `php artisan migrate --force`.
5. Build commands: default (`composer install`, `npm ci && npm run build`).

No seeding needed — visitors create their own events from the home screen, and old ones prune themselves.

## Not built (yet)

- The Expo app itself (the API above is its contract).
- Auth on the dashboard — it is open by design for the prototype; anyone can start a demo event or set event times.
- Per-class section subsets that aren't a 1..N prefix.
- Rate limiting on event creation (worth adding before sharing the URL widely).
