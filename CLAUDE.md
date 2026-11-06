# Vrefos — CLAUDE.md

## Project Overview

Vrefos is a Laravel 13 + NativePHP Mobile app for parents to track toddler/baby activities (feeding, sleeping, etc.). It delivers on-device local notifications reminding parents when it's time for the next feeding or sleep session.

It is a **single-user, on-device app** — there is no authentication, no `User` model, and no remote server. All data lives in on-device SQLite.

## Tech Stack

- **Backend:** PHP 8.5 (mise), Laravel 13, NativePHP Mobile v3
- **Frontend:** Livewire v4, Tailwind CSS v4, daisyUI v5, MaryUI v2 (component prefix: `x-mary-`), Schedule-X v4 (`@schedule-x/calendar` + `theme-default`/`events-service`/`calendar-controls`/`current-time`, `temporal-polyfill`) for the `/planning` calendar
- **Database:** SQLite (on-device via NativePHP; also used locally and in tests)
- **Local Notifications:** `ikromjon/nativephp-mobile-local-notifications` v1.9
- **System settings deep link:** `nativephp/mobile-system` v1 (native handler for `System::appSettings()`, used by the permission banner's "Open settings" button)
- **Dev tools:** Vite + `@tailwindcss/vite`, Laravel Pint (code style), fumeapp/modeltyper (TS types from models)
- **Runtime:** mise manages PHP 8.5 and Node 24 locally

## Running the Project

```bash
php artisan migrate
npm run dev               # Vite dev server
npm run build -- --mode=android   # Build for Android
php artisan native:run android    # Run on Android device/emulator
```

## Key Commands

```bash
php artisan tinker
composer ...                                # Run any composer command
npm ...                                     # Run any npm command
./vendor/bin/pint                           # Format PHP code
```

## Architecture

### Backend

- `app/Models/` — `Baby`, `BabyAction`, `BabyActionEatDetail`, `BabyActionTemperatureDetail`, `BabyActionMedicationDetail`, `BabyActionType`, `Medication`, `MedicationCategory`, `Plan`, `PlanVersion`, `Notification` (no `User` model — the app is single-user; note `App\Models\Notification` name-collides with `Illuminate\Notifications\Notification` only in imports — the app has no Laravel notifications, so it's safe)
- `app/Services/PlanScheduler.php` — Schedules, cancels, and reschedules on-device local notifications for **plans** (recurring routines), replacing the old per-action rules engine. Single chokepoint for all notification logic; all plugin calls go through the `dispatchSchedule()` / `dispatchCancel()` seams, guarded with `function_exists('nativephp_call')` for web/test compatibility. `cancelFor()` only saves the nulled bookkeeping fields when the model still `exists` (delete-event gotcha, same pattern as before). Computes per-unit native payloads (`repeat` / `repeatDays` / `repeatIntervalSeconds` / `repeatCount`) branching on the plan's current `PlanVersion::interval_unit`; Hours-unit plans are one-shot and re-anchored via `reanchorForAction()`.
- `app/Services/PlanTimeline.php` — Pure, stateless projection of a plan's recurrence into calendar slots for a date range (plan-vs-actual): resolves the versioned recurrence active on each day, matches slots against logged `BabyAction`s within a ±45 min tolerance, and reports `done`/`missed`/`upcoming` status. Feeds `Planning\Index::getEvents()`; does not touch the native runtime, so it's safe to unit-test directly.
- `app/Services/NotificationPermission.php` — Wraps the plugin's `checkPermission()` and `System::appSettings()` (native handler from `nativephp/mobile-system`) behind the guarded `dispatchCheck()` / `dispatchOpenSettings()` seam pattern. Note Android never reports `not_determined` — a fresh install reads as `denied` — but the distinction doesn't matter to the UI (one banner for any non-granted status). Off-device (web/tests) `status()` resolves to `Granted`, so no permission UI shows in dev and tests need no native runtime. PHP never *requests* permission — prompting is client-side JS only (see Permission flow).
- `app/Observers/BabyActionObserver.php` — Calls `PlanScheduler::reanchorForAction()` on `created`, `updated` (time/type fields — also re-anchors under the *old* type when `baby_action_type_id` changes), and `deleted`, which re-anchors every enabled Hours-unit plan targeting that action's type and baby. Unlike the old rules system, plan reminders don't depend on Eat/Temperature/Medication detail rows, so there is no "instant-action gotcha" — `created` alone is sufficient, no post-detail rescheduling needed in the Create/Edit pages.
- `app/Observers/BabyObserver.php` — On baby `created`, attaches the new baby to every `all_children` plan (so "all children" stays dynamic as babies are added) and reschedules those plans. Both observers are registered in `AppServiceProvider::boot()`.
- `app/Providers/NativeServiceProvider.php` — Resyncs all plans once per session (5-min cache TTL, `PlanScheduler::rescheduleAll()`) to recover from OS alarm clearing after reboot, and allowlists the NativePHP plugins (`plugins()` array: local notifications, Vrefos assets, mobile-system, device). **Deliberately does not auto-prompt for notification permission** — a bridge call during provider boot fires too early in the app cold start for the OS dialog to appear; the auto-prompt is client-side JS on the banner instead (see Permission flow).
- `app/Enums/FoodType.php` — `breast_milk_left`, `breast_milk_right`, `formula`, `fruits`, `vegetables`, `grains`, `protein`, `dairy`, `other`
- `app/Enums/NotifyFrom.php` — `StartedAt` / `FinishedAt` (Hours-unit plans only — which of the last matching action's timestamps re-anchors the interval)
- `app/Enums/IntervalUnit.php` — `Hours`, `Days`, `Weeks`, `Months` — the recurrence unit the user picks in the plan editor
- `app/Enums/NotificationTrigger.php` — `Start` / `End` — which occurrence edge a message notification fires on
- `app/Enums/FeverLevel.php` — `None`, `Low`, `Medium`, `High`, `TooHigh` (backed string); `fromTemperature(float)` classifies readings; `label()` ("No fever", "Low fever", …); `badgeClass()` for UI severity colors; `rangeLabel()` shows °C boundaries (`< 36.9°C`, `36.9–37.5°C`, etc.) — still used to classify `BabyActionTemperatureDetail` readings, but no longer feeds any notification condition (see below)

### Data Model

```
Baby → hasMany → BabyAction → belongsTo → BabyActionType
                 BabyAction → hasOne    → BabyActionEatDetail
                 BabyAction → hasOne    → BabyActionTemperatureDetail
                 BabyAction → hasOne    → BabyActionMedicationDetail
                                          BabyActionMedicationDetail → belongsTo → Medication
                                          Medication ←→ belongsToMany ←→ MedicationCategory
Plan → belongsTo → BabyActionType
     → hasMany   → PlanVersion
     ←→ belongsToMany ←→ Notification (pivot: notification_plan, includes 'trigger': start|end)
Baby ←→ belongsToMany ←→ Plan (pivot: baby_plan)
```

`BabyAction` fields: `baby_id`, `baby_action_type_id`, `started_at`, `finished_at`. (No longer carries `notification_scheduled_at` / `scheduled_notification_keys` — that bookkeeping moved to `Plan`, since reminders are now per-plan, not per-action.)

`BabyActionEatDetail` fields: `baby_action_id`, `food_type` (nullable, cast to `FoodType` enum). One-to-one with `BabyAction`; cascade-deleted with parent. Only created when action type is "Eat" and a food type is selected.

`BabyActionTemperatureDetail` fields: `baby_action_id`, `temperature` (decimal 4,1). One-to-one with `BabyAction` (instant action); cascade-deleted with parent. Only created for Temperature actions.

`BabyActionMedicationDetail` fields: `baby_action_id`, `medication_id` (FK restrict — medications can't be deleted while actions reference them), `amount_ml` (nullable decimal 6,2). One-to-one with `BabyAction` (instant action); cascade-deleted with parent. Only created for Medication actions.

`Medication` fields: `id`, `name` (string, NOCASE-unique — case-insensitive tag reuse). Has-many `actionDetails()` and many-to-many `categories()`. No longer has any notification-targeting relations (fever-level/medication-targeted rules were dropped, see Notification System).

`MedicationCategory` fields: `id`, `name` (string, NOCASE-unique). Many-to-many `medications()` only.

`Notification` fields: `title` (string, required), `description` (nullable string, blank → empty body). The user's reusable message library — plans attach messages via `notification_plan` rather than each rule owning its own text. Placeholders: `#{baby}`, `#{action}`, `#{interval}` (the plan's recurrence rendered in the user's chosen unit, e.g. "3 hours").

`Plan` fields: `baby_action_type_id`, `starts_on`/`ends_on` (nullable dates, device-local, inclusive — bound the plan's whole life, e.g. a medication course; unversioned, deliberately not on `PlanVersion` so extending a course doesn't rewrite history), `device_timezone` (IANA string, captured from the browser and healed on every Planning page load), `all_children` (bool, default true) + `baby_plan` pivot (same targeting pattern the old rules used), `enabled` (bool, default true), `notification_scheduled_at` / `scheduled_notification_keys` (same OS-alarm bookkeeping `BabyAction` used to carry).

`PlanVersion` fields — **the recurrence definition, versioned by date** so editing a plan's cadence never rewrites history: `plan_id`, `effective_from` (date, unique per plan — `PlanVersion::activeFor($plan, $date)` resolves the latest version with `effective_from <= $date`), `interval_minutes` (int — **always the canonical value in minutes**, e.g. every 3h = 180, daily = 1440), `interval_unit` (cast to `IntervalUnit` — the user's UI selection; `intervalValue()` / `setIntervalFromValue()` convert between stored minutes and the display unit), `times` (json array of `"HH:MM"`, required for Days/Weeks/Months, unused for Hours), `weekdays` (json array of ISO weekdays 1–7, optional, Weeks only), `anchor_date` (cadence origin for multi-day/week cadences and day-of-month for Months), `notify_from` (Hours only), `duration_minutes` (nullable — enables an `end`-trigger notification).

**Child targeting:** a plan targets either **all children** or a **specific subset**, same pattern the old rules used — `all_children` is the authoritative intent flag, the `baby_plan` pivot holds the concrete targeted babies and is kept in sync by `BabyObserver`. An empty specific selection is invalid (the UI treats "no babies" as "all"). The scheduler skips a baby when `! $plan->all_children` and the baby isn't in the pivot.

**Note:** the old rule-conditioning system (fever-level targeting, medication/category targeting, per-action `notify_after_minutes`) was dropped by migration — it doesn't map to the plan model. A plan can be created for a medication cadence instead (e.g. "every 12 hours" with `starts_on`/`ends_on` bounding the course).

### Frontend

- `app/Livewire/Pages/` — Full-page Livewire components (registered via `Route::get('/uri', ComponentClass::class)`)
- `resources/views/livewire/pages/` — Blade views for Livewire page components
- `resources/views/layouts/app.blade.php` — Main app layout (MaryUI `x-mary-main`, sidebar, navbar)
- `resources/js/app.js` — JS entry point. Hosts the **datetime timezone helpers** described below (`utcToLocalInput`, `localInputToUtc`, `formatLocalDateTime` — the latter renders 24-hour time and collapses device-local today/yesterday to `Today HH:mm` / `Yesterday HH:mm`) and `autoRequestNotificationPermission` — the client-side notification-permission auto-prompt (see Permission flow); all on `window`. Imports `resources/js/planning-calendar.js`, which exposes `window.initPlanningCalendar` for the `/planning` calendar (see Notification System). No other notification-scheduling logic lives in JS (notifications are handled natively). Also hosts the **drawer swipe gesture** (self-contained IIFE): below the `lg` breakpoint, a horizontal flick starting anywhere on the content toggles the `#main-drawer` daisyUI checkbox — swipe right opens, swipe left closes. Swipe-anywhere (not edge-only) is deliberate: Android gesture navigation consumes edge touches for the system back gesture. Gestures starting inside `.overflow-x-auto` containers or form controls are ignored; listeners are passive on `document` with lazy checkbox lookup so they survive `wire:navigate` DOM swaps.

**Datetimes & timezones:** `started_at` / `finished_at` are stored as **true UTC instants** (`APP_TIMEZONE=UTC`). The webview is the only place that knows the device's local offset, so all conversion happens in the browser via the `app.js` helpers above: the `datetime-local` inputs use Alpine get/set accessors (`localInputToUtc` on input, `utcToLocalInput` for display), and the list renders times through `formatLocalDateTime`. This keeps the absolute timestamp handed to the OS alarm correct on non-UTC devices. `BabyAction\Create::mount()` seeds `started_at` with the current UTC wall-clock (`now()->format('Y-m-d\TH:i')`), which the form then shows in local time.

The **BabyAction create/edit forms** pick Baby, Action Type, and type-specific fields via always-visible **segmented button groups** (wrapping `x-mary-button`s), not dropdowns — one tap to select. Optional fields (food type, medication) can be deselected by tapping them again; Baby and Action Type cannot (they are required). `Create` also reads `?baby=ID&type=ID` to pre-fill them (the Dashboard quick-start buttons and the Planning sheet's "Log now" use this), auto-selects a lone baby, and returns to the page the user came from after saving. 

- **Eat** type: Food Type (optional; breast milk is split into Left and Right food types).
- **Temperature** type: Temperature °C input (required, 30–45 range); instant action (no Finished At field).
- **Medication** type: Medication tag picker (select existing or type new), optional ml amount; inline category selector (shown while typing new medication name); instant action.

Each field has a `toggle*` action method (`toggleBaby`, `toggleActionType`, `toggleFoodType`, `toggleMedication`) used via `wire:click`; these assign the property directly, so they must call the relevant `updated*` hook themselves (e.g. `toggleActionType` → `updatedBabyActionTypeId`) to fire the clear cascades — direct assignment in an action does **not** trigger Livewire `updated*` hooks the way `wire:model`/`$set` does. `toggleActionType` must `unset($this->selectedActionType)` first (computed property cache invalidation).

Plan reminders don't depend on Eat/Temperature/Medication detail rows (only `baby_id`, `baby_action_type_id`, `started_at`/`finished_at`), so — unlike the old rules system — there is no "instant-action gotcha": `BabyActionObserver::created()` alone re-anchors correctly, and `Create`/`Edit` don't need any post-detail rescheduling call.

The **BabyAction list** (`Pages\BabyAction\Index`) renders one **card per action** (stacked single column, newest first), not a table. Each card shows the type icon + name, baby name, a food-type `badge-info` (top right), stacked `Started:` / `Finished:` lines (via `formatLocalDateTime`) and a server-computed `Total:` duration (Carbon `diffForHumans`, finished actions only). Ongoing actions are marked only by a primary-colored type icon and a small primary **Finish now** button (`o-flag`, one tap, no confirm; an Undo alert appears via the `FinishesActions` trait and `<x-finished-undo>`) — no "Ongoing" badge; `finishNow(BabyAction $babyAction)` stamps `finished_at = now()` (no-op if already finished) and the `BabyActionObserver` then reschedules any `NotifyFrom::FinishedAt` rules automatically. The **whole card navigates to the edit page** — Alpine `@click="Livewire.navigate('{{ route(...) }}')"` with a chevron absolutely positioned at the card's bottom-right (`absolute bottom-5 right-5`, matching the card's `p-5`, so content rows span the full card width) + `active:bg-base-200` press feedback as the affordance; the Finish now button sits in a **shrink-wrapped** (`w-fit`) `@click.stop` wrapper so only the button itself blocks the navigation (a full-width wrapper would create a dead tap strip). List pages carry **no Edit/Delete buttons**: deleting lives on the **edit page** (top-right red button, then a styled confirmation modal, then `redirectRoute(..., navigate: true)` back to the list).

The **Babies list** (`Pages\Baby\Index`) mirrors the same card pattern: one card per baby (name, `badge-ghost` age badge from `Baby::ageLabel()` top-right, optional `Born: d/m/Y` line, chevron affordance, whole-card navigate to `Pages\Baby\Edit`). Deleting a baby lives on the **edit page** (top-right red button opens a **styled modal**, fixed overlay + `bg-base-100` card, ghost Cancel / error Delete — matching the medications/notification-settings pattern, not `wire:confirm`): `promptDelete()` sets `showDeleteModal`, `confirmDelete()` deletes the baby's actions **through Eloquent one-by-one before the baby row** — the `baby_actions.baby_id` DB cascade would skip `BabyActionObserver::deleted` and leave stale OS notifications scheduled — then deletes the baby (the `baby_notification_setting` pivot cascades via FK) and redirects back to the list.

The **Medications page** (`/medications`, `Pages\Medication\Index`) shows two cards: **Medications** (tap a row → `Pages\Medication\Edit`; delete lives there behind a guard check — blocked when logged actions reference the medication) and **Categories** (inline trash button, guarded: warns when medications would lose it, no longer checks notification rules since medications/categories are no longer notification-targetable). Each card header carries an **Add** button: the Medications one links to `Pages\Medication\Create` (`/medications/add`, mirrors the Edit form — unique name, category toggle buttons, "type a new category" input), the Categories one opens an **inline add-category modal** on the index (`openAddCategoryModal` / `createCategory`, unique-name validation). Covered by `tests/Feature/MedicationManagementTest.php`.

The **Planning page** (`/planning`, `Pages\Planning\Index`) is the Google-Calendar-style hub for defining routines and their reminders — see the **Notification System** section below for the full calendar/editor breakdown.

**Blade gotcha (component-tag attributes):** the ComponentTagCompiler does **not** compile directives like `@js()` inside `<x-...>` attribute values — the literal text reaches the browser and Alpine chokes silently. Use `{{ }}` echoes there instead (they *are* compiled); this bit the card's `@click` navigate handler.

### MaryUI Components

All MaryUI components use the `x-mary-` prefix (configured in `config/mary.php`):

| Component | Tag |
|-----------|-----|
| Button | `<x-mary-button>` |
| Input | `<x-mary-input>` |
| Select | `<x-mary-select>` |
| Datepicker | `<x-mary-datepicker>` |
| Table | `<x-mary-table>` |
| Card | `<x-mary-card>` |
| Form | `<x-mary-form>` |
| Alert | `<x-mary-alert>` |
| Toast | `<x-mary-toast>` |
| Toggle | `<x-mary-toggle>` |
| Nav/layout | `<x-mary-nav>`, `<x-mary-main>`, `<x-mary-menu>`, `<x-mary-menu-item>` |

### Routes (web.php)

| Method | URI | Name | Component |
|--------|-----|-------|-----------|
| GET | `/` | `dashboard` | `Pages\Dashboard\Index` |
| GET | `/babies` | `babies.show` | `Pages\Baby\Index` |
| GET | `/babies/add` | `babies.create` | `Pages\Baby\Create` |
| GET | `/babies/{baby}/edit` | `babies.edit` | `Pages\Baby\Edit` |
| GET | `/baby_actions` | `baby_actions.show` | `Pages\BabyAction\Index` |
| GET | `/baby_actions/add` | `baby_actions.create` | `Pages\BabyAction\Create` |
| GET | `/baby_actions/{babyAction}/edit` | `baby_actions.edit` | `Pages\BabyAction\Edit` |
| GET | `/medications` | `medications.show` | `Pages\Medication\Index` |
| GET | `/medications/add` | `medications.create` | `Pages\Medication\Create` |
| GET | `/medications/{medication}/edit` | `medications.edit` | `Pages\Medication\Edit` |
| GET | `/planning` | `planning.show` | `Pages\Planning\Index` |
| GET | `/notifications` | `notifications.show` | `Pages\Notification\Index` |
| GET | `/terms-and-conditions` | — | View `legal.terms-and-conditions` |

Root `/` is the **Dashboard** landing page (`Pages\Dashboard\Index`): per-baby cards showing the **latest 3
actions** of any status (newest first by `started_at`, per-parent `limit(3)` on the eager load). Ongoing rows
(`finished_at IS NULL`, non-instant) show elapsed time and a **Stop** button (one tap, Undo alert); finished rows show when they ended, no
button. No per-action reminder info is shown on the dashboard, but the shared notification-permission banner
(see Permission flow) appears above the quick actions when notifications aren't granted, so the user knows the
app can't notify them. Quick "New Action" / "Add Child" shortcuts
sit above the cards, and each card has quick-start buttons per action type linking to the Create form pre-filled (a type with a running action shows Stop instead). Each card header shows an **age badge** from `Baby::ageLabel()` (`app/Models/Baby.php`,
also used by the Babies list): days under one month (e.g. `26d`, or `newborn`), `N mo` under two years, then
`Ny` / `Ny Nmo`; no badge when `birth_date` is null. Cards are `min-w-0` grid items and the action list is wrapped in `overflow-x-auto` with
`whitespace-nowrap` rows, so long content scrolls inside the card instead of widening the window. The page
uses `wire:poll.60s` to keep elapsed labels fresh.

### Sessions & CSRF (on-device webview)

The NativePHP webview restores the last-rendered DOM when the app reopens, so a page can carry a long-dead
session/CSRF token. To keep that from 419-ing Livewire calls (which pops Livewire's "This page has expired"
modal): the Livewire endpoints (`livewire/*`, `livewire-*` — v4 uses an APP_KEY-derived hashed prefix like
`livewire-a9621833/update`, and NativePHP regenerates APP_KEY per device) and the NativePHP bridge endpoint
(`_native/*`, used by the JS permission auto-prompt) are **CSRF-exempt** in `bootstrap/app.php` (safe:
single-user on-device app, the embedded PHP server is reachable only from the app's own webview), and the
session lifetime defaults to one year (`config/session.php`, `SESSION_LIFETIME` in `.env`).
Regression-guarded by `tests/Feature/CsrfExemptionTest.php` (middleware-level, because Laravel skips CSRF
verification entirely under unit tests).

## Notification System

Reminders are delivered as **on-device local notifications** via `ikromjon/nativephp-mobile-local-notifications`. No server, no internet required. The reminder engine is **plan-based**: users define recurring routines (a `Plan`) on a Google-Calendar-style calendar at `/planning`, and attach reusable **messages** (`Notification`, managed at `/notifications`) to fire at the plan's start and/or end. This replaced an earlier per-action-type rules engine (fever-level/medication-targeted conditions, one rule per condition set) that grew too complicated; that system's tables and code were dropped by migration — no data migration, since old rules don't map to the new model.

**Flow (`app/Services/PlanScheduler.php`):**
1. `BabyActionObserver` (`created`/`updated`/`deleted`) calls `PlanScheduler::reanchorForAction()`, which re-anchors every **enabled Hours-unit** plan targeting that action's type and baby (re-anchoring means: cancel + fully reschedule that plan).
2. `scheduleFor(Plan $plan)` resolves the plan's **current** `PlanVersion` (`PlanVersion::activeFor($plan, today())`) and, for every targeted baby × attached notification × trigger × occurrence, computes a native payload and calls `LocalNotifications::schedule([...])`.
3. **Per-unit occurrence mechanics** (branches on `interval_unit`): **Hours** — one-shot, re-anchored `at` = last matching action's (`started_at`|`finished_at` per `notify_from`) + `interval_minutes`; no action yet → `now()+interval`; already past → clamp `now()+1s`. **Days**(=1440min)/**Weeks**(with `weekdays`) — native `repeat: 'daily'` / `repeatDays`, one alarm per `times` entry. **Days**(>1440min)/**Weeks**(no `weekdays`) — `repeatIntervalSeconds`, next occurrence computed from `anchor_date`. **Months** — `repeat: 'monthly'`, next occurrence of `anchor_date`'s day-of-month.
4. **Triggers:** `start` = the occurrence time itself. `end` = occurrence + `duration_minutes` (Hours unit: anchored to the last action's `finished_at` instead, skipped entirely when that action isn't finished yet).
5. **Range bounds:** `starts_on`/`ends_on` clamp/skip occurrences (device-local days); repeating payloads get a computed `repeatCount` when `ends_on` is set (open-ended plans omit it, unchanged native behavior) so the OS alarm self-terminates even if the app is never reopened.
6. Scheduled keys (`plan-{planId}-baby-{babyId}-notif-{notificationId}-{trigger}[-t{timeIndex}]`) are stored in `Plan::scheduled_notification_keys`; `notification_scheduled_at` is set when at least one key was scheduled. No attached notification for a trigger → nothing scheduled for it; no attachments at all → the plan is calendar-only.
7. On app boot (`NativeServiceProvider`): `PlanScheduler::rescheduleAll()` runs once per session (cached 5 min) to recover OS alarms cleared after device reboot, and heals stale `repeatIntervalSeconds` DST drift by recomputing `at` from scratch.

**Read side — `app/Services/PlanTimeline.php`:** a separate, pure/stateless class projects a plan's recurrence into calendar slots for plan-vs-actual review (used by `Planning\Index::getEvents()`, not the scheduler). Everywhere it reads a recurrence field, it resolves the `PlanVersion` active **on the date being projected** — so a range spanning a recurrence edit shows old-rule days before the edit and new-rule days after, in the same query. Hours-unit plans use chained projection (anchor = last matching action, or the earliest known action if there's no lookback; each logged action resets the chain); clock-based plans resolve each day's slots independently. Matching is nearest-first within `PlanTimeline::SLOT_MATCH_TOLERANCE_MINUTES = 45`.

**Permission flow:** `NotificationPermission` is the single wrapper around the plugin's permission API. The OS dialog opens **automatically after the page renders**, from the **browser side**: the banner carries `x-data x-init="window.autoRequestNotificationPermission?.()"` (helper in `resources/js/app.js`), which — after an ~800 ms settle delay, once per webview session via a `sessionStorage` guard set **only on success** — POSTs straight to NativePHP's `/_native/api/call` bridge endpoint with `{method: 'LocalNotifications.RequestPermission'}` and swallows all failures. It is deliberately **not** a Livewire request and **not** a provider-boot bridge call: boot-time calls fire too early in the cold start for the dialog to appear, and a failed Livewire request at startup pops Livewire's error modal ("404"/"page expired") on top of the app (both tried and failed). This is the **only** prompting mechanism — there is no prompt button. Android allows **two denials total**, after which the OS permanently blocks the dialog and further requests silently no-op. The banner UI is shared by the **Dashboard**, **Planning**, and **Notifications** pages through the `App\Livewire\Concerns\HandlesNotificationPermission` trait (a `permissionStatus` property set by its mount hook, `refreshPermissionStatus` / `openAppSettings` actions, and `#[OnNative(PermissionGranted::class)]` / `#[OnNative(PermissionDenied::class)]` handlers that update `permissionStatus` live, since the dialog result arrives asynchronously) plus the `<x-notification-permission-banner :status="...">` Blade component: **one red banner** ("Notifications are disabled") for any non-granted status — a vertical daisyUI alert whose single bottom button, **"Open settings"**, opens the app's screen in device settings via `System::appSettings()` (native `System.OpenAppSettings` handler from the `nativephp/mobile-system` plugin, allowlisted in `NativeServiceProvider::plugins()`) — the only recovery once Android blocks the dialog. Because granting via the system Settings screen fires **no native event**, the banner also carries `wire:poll.5s="refreshPermissionStatus"`, so it clears within seconds of the user returning. Tests fake the service with a subclass bound in the container (`tests/Feature/NotificationPermissionTest.php`, which also covers the dashboard banner).

### Calendar page `/planning` (Schedule-X)

`app/Livewire/Pages/Planning/Index.php` + `resources/views/livewire/pages/planning/index.blade.php`. Uses **Schedule-X** (`@schedule-x/calendar` + `@schedule-x/theme-default`/`events-service`/`calendar-controls`/`current-time`, plus `temporal-polyfill` — Schedule-X v4 events use the Temporal API), bundled via Vite (offline app, no CDN). `resources/js/planning-calendar.js` exposes `window.initPlanningCalendar($wire, el)`, called from a `wire:ignore x-data x-init="window.initPlanningCalendar($wire, $el)"` div so Livewire never touches the calendar's DOM; a `data-calendars` attribute carries the per-action-type color config (JSON) plus a muted `planned` calendar. Three views, selectable in the header: **Day** (time grid, the default, with Google-Calendar-style overlap — `weekOptions.eventOverlap: true` stacks concurrent events indented and on top of each other, and a small `padOverlappedEvents()` pass pads each earlier event's right edge so its text only wraps in the part that stays visible), **Month** (`createViewMonthAgenda`: dots per day, the selected day's events listed as full-width cards) and **List** (every event its own row). Events are loaded through the **`fetchEvents` callback** (`$wire.getEvents(from, to, timeZone)`), *not* `onRangeUpdate` — Schedule-X deliberately skips `onRangeUpdate` for the first range, so using it leaves the calendar empty until the user navigates. `getEvents()` returns ISO-8601 times with an offset, which the JS converts to the `Temporal.ZonedDateTime` objects Schedule-X v4 requires (it throws on anything else). The calendar listens for the Livewire event `planning-events-changed` (dispatched on filter changes and plan save/delete/toggle, since the calendar is `wire:ignore`) and also re-queries every 60 s. `resources/css/planning-calendar.css` maps Schedule-X's CSS variables onto the daisyUI tokens (so it follows light/dark automatically) and restyles the header for a phone; note `--sx-border` has to be re-declared there because Schedule-X resolves it on `:root`. `onEventClick` no longer navigates directly: it calls `$wire.showEvent(id, title, startInstant, endInstant|null)`, which opens a **details sheet** (`x-mary-modal` with `modal-bottom sm:modal-middle`, so a bottom sheet on phones) built from `$selectedEvent` — title, `Fri 6 Nov · 07:30 – 07:55` (end only when `hasEnd`), then child name plus Eat food type / Temperature with fever level / Medication name and ml / `Ongoing` for logged actions, or `Missed`/`Next up`/`Planned`, child and recurrence for planned slots. Its buttons are **Close** and **Open action** (`wire:navigate` link to `baby_actions.edit`) or **Edit plan** (`openPlanFromEvent()` → `openEdit()`). This matters on touch screens: there is no hover to raise an event that is covered by another one (desktop hover still does, via CSS `z-index`), and a mis-tap no longer lands on an edit form. The refresh listener uses `Livewire.on(...)` because only it returns a cleanup function (`$wire.$on` does not, and calling its result throws on navigation).

`getEvents(string $from, string $to, ?string $timezone = null): array` (the browser passes its own zone because the first fetch can race ahead of `setDeviceTimezone()`; falls back to `device_timezone`) returns two kinds of events: logged `BabyAction`s as blocks (`id: 'action-{id}'`, `calendarId: 'type-{actionTypeId}'`, at least 15 minutes long so instant actions aren't zero-height), and `PlanTimeline`-projected slots (`id: 'plan-{planId}-{babyId}-{timestamp}'`, `calendarId: 'planned'`, status-prefixed title: `✗ ` missed / `→ ` next-upcoming). A **done** slot is not drawn separately — the logged action that satisfied it (`matched_action_id`) gets the `✓ ` title prefix instead, so the grid doesn't show two overlapping blocks for one event. Every event also carries `hasEnd` (true only for a finished duration action, or a planned slot whose plan has `duration_minutes`; the padded `end` exists only to give the time grid a visible block). List and Month agenda show `HH:MM – HH:MM` under the title, or just the start when `hasEnd` is false (List via an `is-point` row class set through `_options.additionalClasses`, Month agenda via `_customContent.monthAgenda`); Day view blocks show the same line (via `_customContent.timeGrid`) with container queries adapting it to the block: tall blocks put `start – end` under the title; blocks under ~35px tall (15-minute events, most planned slots) show only the start time in front of the title on one line (`08:00 ✗ Medication — Mia`, title truncated with an ellipsis); the visible sliver of an overlapped earlier event (under ~99px of width) shows the title only. A **baby filter** (daisyUI chips) shown when there's more than one baby restricts both event kinds *and* the Manage-plans list (plans for all children plus plans targeting the selected babies) to `activeBabyIds`.

The page root carries `x-init="$wire.setDeviceTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"`; `setDeviceTimezone()` validates against `timezone_identifiers_list()`, then updates+reschedules any plan whose stored `device_timezone` differs (heals travel/DST whenever the page opens).

**Manage plans** section above the calendar (calendar stays the hero) lists every plan as a card, matching the old rules-page card pattern (`shadow-sm cursor-pointer active:bg-base-200 transition-colors`, absolutely positioned chevron, inline `@click.stop`-wrapped enable toggle). Tapping a card opens the **plan editor** modal (`openEdit($id)`), same NotificationSettings-era conventions: scoped `<style>` block hides the modal's built-in close-X (`.plan-modal .modal-box > form > button.absolute`) so Cancel/Save/backdrop/Escape remain the only close paths; delete lives in the modal's own header row (`promptDeletePlan`, closes the edit modal before opening a hand-rolled `z-40` delete-confirm overlay so it isn't hidden behind the daisyUI modal). Editor fields: action-type + repeat-unit segmented buttons (unit toggle clears fields that don't apply), a single "Repeat every [N] [unit]" numeric input (`PlanVersion::intervalValue()`/`setIntervalFromValue()` convert to/from stored minutes), per-unit extras (`times` rows with add/remove for Days/Weeks/Months, weekday chips for Weeks, cadence anchor date, `notify_from` for Hours), optional duration, `starts_on`/`ends_on` date range, child targeting, and two notification-attachment chip rows (start/end — end row hidden until a duration is set) each with an inline "new message" quick-add that creates a `Notification` and attaches it in one action. **Saving recurrence changes never mutates a past `PlanVersion` row** — `savePlan()` looks up (or creates) the row for `effective_from = today` via `whereDate()` (an exact-match `firstOrNew()` would miss same-day re-edits, since the `date` cast serializes with a `00:00:00` time suffix) and updates that; plan-level columns (`starts_on`/`ends_on`/targeting/`enabled`) update in place on `plans` since they're deliberately unversioned.

### Messages page `/notifications`

`app/Livewire/Pages/Notification/Index.php` + blade — tiny CRUD, card-list pattern like Medications: list messages (title + description), add/edit inline `x-mary-modal`, delete via a hand-rolled confirm overlay. Deleting/editing a message reschedules every plan it's attached to (`Notification::plans()` before delete, `Plan->plans` after edit) so the OS alarms' resolved title/body stay in sync.

## Testing

PHPUnit 12 — run with:

```bash
php artisan test --compact
```

Tests run against SQLite (the same engine as production). Feature tests use the `RefreshDatabase` trait, so no external database service is required. Livewire page components are tested with `Livewire::test(Component::class)`.

## Code Style

PHP: Laravel Pint (run `./vendor/bin/pint` before committing).

## Documentation Lookup

When looking up docs for any library, framework, SDK, API, or CLI tool, always use **Context7 MCP** first (`resolve-library-id` → `query-docs`). Do not fall back to web search without explicitly asking the user for permission first.

## Note on the auto-generated Laravel Boost block below

The `<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- livewire/volt (VOLT) - v1
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v13
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== volt/core rules ===

# Livewire Volt

- Single-file Livewire components: PHP logic and Blade templates in one file.
- Always check existing Volt components to determine functional vs class-based style.
- IMPORTANT: Always use `search-docs` tool for version-specific Volt documentation and updated code examples.
- IMPORTANT: Activate `volt-development` every time you're working with a Volt or single-file component-related task.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== ikromjon/nativephp-mobile-local-notifications rules ===

<local-notifications-guidelines>

# Local Notifications Plugin — AI Guidelines

## Facade

```php
use Ikromjon\LocalNotifications\Facades\LocalNotifications;
```

### Methods

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `schedule($options)` | `NotificationOptions\|array` | `array` | Schedule a notification with delay, timestamp, repeat, actions, etc. |
| `cancel($id)` | `string` | `array` | Cancel a notification by ID. Also cancels day-of-week sub-alarms. |
| `cancelAll()` | — | `array` | Cancel all scheduled notifications. |
| `getPending()` | — | `array` | List all pending notifications. Day-of-week sub-alarms are aggregated. |
| `requestPermission()` | — | `array` | Request notification permission (Android 13+, iOS). |
| `checkPermission()` | — | `array` | Check current permission status (`granted`, `denied`, `notDetermined`). |
| `update($id, $options)` | `string`, `NotificationOptions\|array` | `array` | Update an existing notification's content or timing. |

### Schedule Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string | Yes | Unique notification identifier |
| `title` | string | Yes | Notification title |
| `body` | string | Yes | Notification body text |
| `delay` | int | No | Seconds from now |
| `at` | int | No | Unix timestamp |
| `repeat` | RepeatInterval\|string | No | `minute`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` |
| `repeatIntervalSeconds` | int | No | Custom interval >= 60s. Mutually exclusive with `repeat` |
| `repeatDays` | array\<int\> | No | ISO weekdays (1=Mon..7=Sun). Requires `at`. Mutually exclusive with `repeat` |
| `repeatCount` | int | No | Limit repetitions (min 1) |
| `sound` | bool | No | Default from `config('local-notifications.default_sound')`, initially `true` |
| `badge` | int | No | App icon badge (iOS) |
| `data` | array | No | Custom payload passed through to events |
| `subtitle` | string | No | iOS subtitle / Android subtext |
| `image` | string | No | http/https URL for rich notification image |
| `bigText` | string | No | Expanded text on notification pull-down |
| `actions` | array | No | Action buttons (limit from `config('local-notifications.max_actions')`, default 3): `[{id, title, destructive?, input?}]` |

### Type-Safe DTOs

```php
use Ikromjon\LocalNotifications\Data\NotificationOptions;
use Ikromjon\LocalNotifications\Data\NotificationAction;
use Ikromjon\LocalNotifications\Enums\RepeatInterval;

LocalNotifications::schedule(new NotificationOptions(
    id: 'habit-1',
    title: 'Drink Water',
    body: 'Stay hydrated!',
    at: now()->setTime(9, 0)->timestamp,
    repeat: RepeatInterval::Daily,
    actions: [
        new NotificationAction(id: 'done', title: 'Done'),
        new NotificationAction(id: 'snooze', title: 'Snooze'),
    ],
));
```

## Events

| Event | Payload | When |
|-------|---------|------|
| `NotificationScheduled` | `id`, `title`, `body` | Notification successfully scheduled |
| `NotificationUpdated` | `id`, `title`, `body` | Notification successfully updated |
| `NotificationReceived` | `id`, `title`, `body`, `data?` | Notification delivered to device |
| `NotificationTapped` | `id`, `title`, `body`, `data?` | User tapped the notification |
| `NotificationActionPressed` | `notificationId`, `actionId`, `data?`, `inputText?` | User pressed an action button |
| `PermissionGranted` | — | Permission granted |
| `PermissionDenied` | — | Permission denied |

Events are dispatched to **all** contexts simultaneously. Listen in whichever fits your stack:

### Livewire

```php
#[OnNative(NotificationTapped::class)]
public function onTapped($data) { /* $data['id'], $data['data'] */ }
```

### Laravel Listeners (works with native UI / EDGE — no WebView needed)

```php
// app/Listeners/HandleNotificationTap.php
use Ikromjon\LocalNotifications\Events\NotificationTapped;

class HandleNotificationTap
{
    public function handle(NotificationTapped $event): void
    {
        // $event->id, $event->title, $event->body, $event->data
    }
}
```

### JavaScript (Inertia / Vue / React)

```js
import { schedule, cancel, Events } from '../../vendor/ikromjon/nativephp-mobile-local-notifications/resources/js/index.js';
import { On } from '#nativephp';

await schedule({ id: 'r1', title: 'Reminder', body: 'Hello', delay: 60 });
await cancel('r1');

On(Events.NotificationTapped, (payload) => {
    console.log('Tapped:', payload.id, payload.data);
});
```

## Configuration (v1.4.0)

Publish with `php artisan vendor:publish --tag=local-notifications-config`.

| Key | Default | Platform | Description |
|-----|---------|----------|-------------|
| `channel_id` | `nativephp_local_notifications` | Android | Notification channel ID |
| `channel_name` | `Local Notifications` | Android | Channel name in device settings |
| `channel_description` | `Notifications scheduled by the app` | Android | Channel description |
| `max_actions` | `3` | Both | Max action buttons per notification |
| `min_repeat_interval_seconds` | `60` | Both | Minimum custom repeat interval |
| `default_sound` | `true` | Both | Play sound when no explicit `sound` parameter |
| `tap_detection_delay_ms` | `500` | Android | Warm-start tap detection delay (advanced) |
| `navigation_replay_duration_ms` | `15000` | Android | Cold-start event replay window (advanced) |

Config is injected into **every** bridge call via `_config` key — both Android (Kotlin) and iOS (Swift) read applicable values at runtime, even before the first `schedule()` call.

## Cold-Start Tap Events

Add the init Blade component once to your app layout **after `<script src="http://localhost/livewire-a9621833/livewire.min.js?id=657d57c2"   data-csrf="" data-module-url="http://localhost/livewire-a9621833" data-update-uri="http://localhost/livewire-a9621833/update" data-navigate-once="true"></script>`** to auto-flush cold-start tap events:

```blade
@livewireScripts
<x-local-notifications::init />
```

This waits for `livewire:navigated` (after components are hydrated), then triggers a `CheckPermission` bridge call to flush any queued `NotificationTapped` events. No manual `checkPermission()` in `mount()` needed.

**Important:** Must be placed after `@livewireScripts`, not in `<head>`. Flushing before component hydration causes events to be silently lost.

## Event Dispatch & Tap Detection

- **Pending event flush:** Every bridge function flushes any queued pending events (e.g. `NotificationTapped` from a cold-start tap). The first bridge call after app launch delivers all queued events. The `<x-local-notifications::init />` Blade component automates this by triggering a `CheckPermission` bridge call on page load.
- **Warm-start tap detection (Android):** An `Application.ActivityLifecycleCallbacks` runs `detectTappedNotifications()` on every `onResume` with configurable delay (`tap_detection_delay_ms`). When the user taps a notification while the app is open, the event fires immediately when the app returns to foreground — no bridge call needed.
- **Cold-start navigation replay (Android):** On cold start, a `livewire:navigated` JS listener replays `NotificationTapped` on every `wire:navigate` navigation for configurable duration (`navigation_replay_duration_ms`). This ensures the event reaches the destination page's `#[OnNative]` handlers even when the first bridge call runs on a different page.
- **SharedPreferences-based tap tracking (Android):** When a notification fires, a tap payload is stored. On user swipe-dismiss, a `deleteIntent` clears it. On user tap (auto-cancel), the payload persists. The plugin compares stored payloads against `NotificationManager.getActiveNotifications()` to detect taps.
- **Android `livewire:init` fallback:** On cold start, Livewire may not be loaded when native events are dispatched. The plugin injects a `livewire:init` JS listener as a fallback — events are replayed when Livewire initializes. This is automatic and requires no user action.
- **iOS limitation:** The `livewire:init` and `livewire:navigated` fallbacks are Android-only. On iOS, the plugin relies on the NativePHP core's WebView user script for Livewire dispatch. If Livewire timing is an issue on iOS cold start, ensure a bridge call (e.g. `checkPermission()`) happens after the page loads.

## Native Code Architecture

### Android (Kotlin) — `resources/android/src/`

| File | Purpose |
|------|---------|
| `LocalNotificationsFunctions.kt` | Bridge functions (`Schedule`, `Cancel`, `CancelAll`, `GetPending`, `Update`, `RequestPermission`, `CheckPermission`). Each inner class implements `BridgeFunction.execute()`. Uses `initBridgeCall()` for common setup (delegate, activity, config extraction). Also holds `ActivityHolder`, tap detection, pending event queue, and Livewire fallback JS injection. |
| `NotificationScheduler.kt` | Shared utilities extracted from bridge functions. Contains `NotificationParams` data class, parameter parsing (`parseParams`, `mergeParams`), trigger/repeat calculation, alarm scheduling/cancellation, SharedPreferences persistence, day-of-week alarm management, and event dispatch helpers. |
| `LocalNotificationReceiver.kt` | `BroadcastReceiver` that fires when AlarmManager triggers. Builds and displays the notification, dispatches `NotificationReceived` event, handles self-rescheduling for repeats, and manages dismiss intents for tap detection. |
| `NotificationTapReceiver.kt` | Handles notification tap broadcasts (fallback path). |
| `NotificationActionReceiver.kt` | Handles action button press broadcasts, extracts `RemoteInput` text for input actions. |
| `BootReceiver.kt` | Restores alarms from SharedPreferences after device reboot. Uses `NotificationScheduler.calculateNextTrigger()` for calendar-based repeats. |

### iOS (Swift) — `resources/ios/Sources/`

| File | Purpose |
|------|---------|
| `LocalNotificationsFunctions.swift` | Bridge functions (`Schedule`, `Cancel`, `CancelAll`, `GetPending`, `Update`, `RequestPermission`, `CheckPermission`). Each inner class conforms to `BridgeFunction`. Uses `initBridgeCall()` for common setup. Also holds `LocalNotificationDelegate` (UNUserNotificationCenterDelegate) for tap/receive/action event handling. |
| `NotificationHelper.swift` | Shared utilities: `buildContent()` (UNMutableNotificationContent), `registerActions()` (UNNotificationCategory), `attachImage()` (URL validation + download), `buildTrigger()` (delay/timestamp/repeat → UNNotificationTrigger), `scheduleDayOfWeekRequests()`, and `extractCustomData()`. |

### Key Patterns in Native Code

- **`initBridgeCall()`**: Every bridge function calls this first. It extracts the NativePHP delegate, gets the current activity/dispatch queue, and reads config values. Eliminates ~15 lines of boilerplate per function.
- **`NotificationParams` (Android)**: Data class that holds parsed notification parameters (id, title, body, sound, badge, data, subtitle, image, bigText, actions). Created via `NotificationScheduler.parseParams()`.
- **Merge semantics (Update)**: New parameters override existing stored values; missing parameters fall back to the stored notification's values. Uses `NotificationScheduler.mergeParams()` (Android) or manual JSON merging (iOS).
- **Day-of-week sub-IDs**: `repeatDays` creates one alarm/request per day with ID format `{id}_day_{isoDay}`. Parent tracking enables `cancel()` and `getPending()` to aggregate them.
- **Self-rescheduling repeats (Android)**: Instead of `setRepeating()`, each alarm fires once and `LocalNotificationReceiver` schedules the next occurrence via `setExactAndAllowWhileIdle()`.

## Common Patterns

- Always call `requestPermission()` before scheduling (Android 13+, iOS).
- Use `update(id, options)` to modify an existing notification — it merges new values with existing ones.
- `repeatDays` creates one sub-alarm per day — `cancel()` and `getPending()` handle aggregation automatically.
- Notification IDs should be deterministic (e.g. `habit-{id}`) so you can cancel without tracking state.
- `data` payload is passed through to `NotificationTapped` and `NotificationActionPressed` events.
- To ensure `NotificationTapped` events are delivered on cold start, add `<x-local-notifications::init />` to your layout, or call at least one bridge function (e.g. `checkPermission()`) early in the page lifecycle.
- Action buttons: Visible when the user expands (swipe down) the notification. Limit configurable via `max_actions` (default 3).

## Required Permissions

Declared automatically via `nativephp.json` — no manual setup needed.

**Android:** `POST_NOTIFICATIONS`, `SCHEDULE_EXACT_ALARM`, `RECEIVE_BOOT_COMPLETED`, `VIBRATE`. `SCHEDULE_EXACT_ALARM` is denied by default on Android 14+; when not granted, notifications fall back to inexact alarms (may be delayed under Doze).
**iOS:** Runtime authorization for alert, sound, badge. Min version: 18.0 (NativePHP baseline).

No environment variables or API keys required.

</local-notifications-guidelines>

=== nativephp/mobile rules ===

## NativePHP Mobile

- NativePHP Mobile is a Laravel package for building native iOS and Android apps using PHP and native UI components. It runs a full PHP runtime directly on the device with SQLite — no web server required.
- Documentation: `https://nativephp.com/docs/mobile/3/**`
- IMPORTANT: Always activate the `nativephp-mobile` skill every time you work on any NativePHP functionality.

### Build Commands — Tell the User, Never Run

**CRITICAL: Never execute any of these commands yourself. Always instruct the user to run them manually in their terminal.**

| Command | Purpose |
|---|---|
| `npm run build -- --mode=ios` | Build frontend assets for iOS |
| `npm run build -- --mode=android` | Build frontend assets for Android |
| `php artisan native:run ios` | Compile and run on iOS simulator/device |
| `php artisan native:run android` | Compile and run on Android emulator/device |
| `php artisan native:run ios --watch` | Build, deploy, then start hot reload — all in one |
| `php artisan native:watch` | Hot reload (watch for file changes) |
| `php artisan native:open` | Open project in Xcode or Android Studio |

**Always ask which platform before giving any build or run command.** If the user hasn't specified iOS or Android, ask: "Which platform do you want to build/test on — iOS or Android?" Never assume a platform.

When the platform is confirmed, give the relevant command(s) above and tell the user to run it in their terminal. Do not run it yourself.
</laravel-boost-guidelines>

=== nativephp/mobile-system rules ===

## nativephp/system

System-level operations for NativePHP Mobile apps.

### PHP Usage (Livewire/Blade)

<code-snippet name="System Operations" lang="php">
use Native\Mobile\Facades\System;

// Open app settings
System::openAppSettings();
</code-snippet>

### JavaScript Usage (Vue/React/Inertia)

<code-snippet name="System Operations in JavaScript" lang="javascript">
import { system } from '#nativephp';

// Open app settings
await system.openAppSettings();
</code-snippet>

### Methods

- `System::openAppSettings()` - Opens app's settings screen in device settings

### Use Cases

Direct users to grant permissions after initial denial, allow users to change notification preferences, enable users to manage app-specific settings.

</laravel-boost-guidelines>

</laravel-boost-guidelines>
