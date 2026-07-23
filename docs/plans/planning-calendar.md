# Planning Calendar with Custom Notifications (replaces the rules system)

## Context

The notification-rules page (`/notification-settings`) grew too complicated; what the user actually wants is **planning**: define the kid's routine, see it on a **Google-Calendar-style calendar (Schedule-X, Month ⇄ Day views)**, and attach **their own notification messages** to plans. Decisions made with the user:

- **Recurrence, easy to define**: every N hours (re-anchored from the last *logged* action — logging a feed resets the timer), daily at chosen times ("5 meals a day"), weekly on chosen days, monthly, or every N days. **Stored canonically as minutes** in a single column, with one extra column recording the unit the user picked (hours/days/weeks/months); the UI converts back for display ("every 3 hours" = `180` + `hours`).
- **Custom notifications**: the old `notification_settings` machinery (fever levels, medication targeting, notify-after-minutes rules) is **dropped by migration now**. A new lean `notifications` table holds only `title` + `description`; plans attach them via a pivot. Notify **at start, at end, both, or none** — expressed by *which* notifications are attached and with which trigger.
- **Calendar UI**: Schedule-X (user's choice over FullCalendar; nothing hand-rolled), month view showing the baby's actions per day, day view as a time-axis timeline, switchable like Google Calendar.
- Plan vs actual: planned slots show done/missed/upcoming against logged `BabyAction`s.

## Data model

### `notifications` (new table — the user's message library)
| column | type |
|---|---|
| `title` | string required |
| `description` | nullable string (blank → empty body) |

Placeholders supported in both: `#{baby}`, `#{action}`, `#{interval}` (the plan's recurrence rendered in the user's chosen unit, e.g. "3 hours"). Model `app/Models/Notification.php` (⚠ name-collides with `Illuminate\Notifications\Notification` only in imports — the app has no Laravel notifications, so it's safe; alias where needed).

### `notification_plan` pivot
`plan_id` FK cascade, `notification_id` FK cascade, `trigger` string — `start` | `end` (new enum `App\Enums\NotificationTrigger`). One plan can attach many messages; the same message is reusable across plans. **No pivot rows → the plan is calendar-only (no reminders). This is how "none" works; "both" = one attachment per trigger.**

### `plans`
| column | notes |
|---|---|
| `baby_action_type_id` | FK; any type |
| `interval_minutes` | int — **the single canonical recurrence value, always minutes** (every 3h = 180; daily = 1440; every 3 days = 4320; weekly = 10080; monthly normalized to 43200) |
| `interval_unit` | enum `App\Enums\IntervalUnit`: `Hours`, `Days`, `Weeks`, `Months` — **the user's UI selection**; the editor shows `interval_minutes` converted back to this unit, and the scheduler branches on it for behavior (see per-unit table) |
| `times` | json array of local `"HH:MM"` — required when unit is Days/Weeks/Months (multiple entries = e.g. 5 meals a day); unused for Hours |
| `weekdays` | json array of ISO days 1–7 — optional, unit `Weeks` only ("specific days") |
| `anchor_date` | date — cadence origin for multi-day/week cadences ("every 3 days" counts from here) and the day-of-month for Months; defaults to creation date |
| `notify_from` | reuse `NotifyFrom` enum — Hours unit only: whether the interval counts from the last action's `started_at` or `finished_at` |
| `duration_minutes` | nullable — planned duration; required to attach an `end`-trigger notification on clock-based units (end = planned time + duration) |
| `device_timezone` | IANA string captured from the browser at save, refreshed when the calendar page loads |
| `all_children` | bool default true + `baby_plan` pivot (`baby_id`/`plan_id` FKs cascade) — same targeting pattern as before; `BabyObserver` attaches new babies to all-children plans |
| `enabled` | bool default true |
| `notification_scheduled_at`, `scheduled_notification_keys` | same bookkeeping pattern as `baby_actions` has today |

Files: `app/Models/Plan.php`, `app/Models/Notification.php`, enums, `database/factories/{Plan,Notification}Factory.php` (factory states per kind). No seeded defaults.

### Demolition migration (single migration, after the new tables exist)
- Drop `notification_setting_fever_levels`, `medication_notification_setting`, `medication_category_notification_setting`, `baby_notification_setting`, then `notification_settings`.
- Drop `baby_actions.notification_scheduled_at` + `scheduled_notification_keys` (per-action scheduling dies with the rules; plans carry their own bookkeeping).
- **No data migration** — old rules don't map to the new model (user accepts losing fever-level / medication-targeted reminders; a plan can be created for medication cadence instead).

### Code removal
- `app/Models/NotificationSetting.php`, `NotificationSettingFeverLevel.php`; `app/Services/LocalNotificationScheduler.php` (its dispatch seams + `SOUND_NAME` move to the new service, see below); `app/Livewire/Pages/NotificationSettings/Index.php` + blade; the `/notification-settings` route + "Notifications" menu item (replaced, see Navigation); the default-rules seeding migration stays in history but its target table is gone — the drop migration runs after it, fine.
- `Baby/Edit`, `Medication/Edit|Index` guard logic referencing notification rules: remove rule checks, keep logged-action checks. `BabyActionEatDetail` etc. untouched.
- Tests deleted: `LocalNotificationSchedulerTest`, `NotificationSettingsTest`, `DefaultNotificationRulesTest`. Kept: `NotificationPermissionTest` (permission banner is independent and stays), `CsrfExemptionTest`.

## Scheduling engine — `app/Services/PlanScheduler.php`

Same seam pattern as today (protected `dispatchSchedule(array)` / `dispatchCancel(string)` guarded by `function_exists('nativephp_call')`; `SOUND_NAME = 'brefos_notification.wav'` moves here). Public API:
- `scheduleFor(Plan $plan): bool` — cancel stored keys, then for each targeted baby × each attached notification (with trigger) × each occurrence source, dispatch and persist keys via `saveQuietly()`. Disabled plan or no attachments → cancel only.
- `cancelFor(Plan)` (guard `$plan->exists` before saving — delete-event gotcha), `rescheduleFor(Plan)`, `rescheduleAll(): int` (boot resync), `reanchorForAction(BabyAction $action)` — reschedules enabled **Interval** plans of the action's type targeting the action's baby.

**Per-unit payloads** (all `at` values computed in `device_timezone` → UTC unix; content resolved from the attached notification's title/description with placeholders). Storage is always `interval_minutes`; **behavior branches on `interval_unit`**:
| unit | mechanism |
|---|---|
| Hours | **re-anchored interval**: one-shot `at` = last matching action's (`started_at`\|`finished_at` per trigger) + `interval_minutes`; no action yet → `now() + interval`; past → clamp `now()+1s`. Re-anchored by `BabyActionObserver` on created/updated/deleted (on type change re-anchor old + new type via `getOriginal`). |
| Days, value = 1 day (1440) | `repeat => 'daily'`, one per entry in `times`, `at` = next local occurrence |
| Days, value > 1 day | `repeatIntervalSeconds => interval_minutes * 60`, `at` = next cadence occurrence from `anchor_date` at each `times` entry |
| Weeks + `weekdays` set | `repeatDays => weekdays`, `at` = next occurrence (plugin creates per-day sub-alarms; `cancel()` aggregates) |
| Weeks, no `weekdays` | `repeatIntervalSeconds => interval_minutes * 60`, `at` = next cadence from `anchor_date` |
| Months | `repeat => 'monthly'`, `at` = next occurrence of `anchor_date`'s day-of-month — calendar-correct scheduling even though storage normalizes a month to 43200 min |

**Triggers:** `start` = the occurrence time itself (Hours unit: anchored to last action's `started_at`). `end` = occurrence + `duration_minutes` (Hours unit: anchored to last action's `finished_at` — skip when the last action isn't finished). Validation stops `end` attachments on clock-based plans without a duration.

**Keys:** `plan-{planId}-baby-{babyId}-notif-{notificationId}-{trigger}[-t{timeIndex}]` — deterministic, stored in `scheduled_notification_keys` for robust cancel.

**Why native repeats** (not one-shot + resync): repeats keep firing even if the app isn't opened for days. DST drift (fixed-interval repeats) heals automatically because `NativeServiceProvider::boot()` resync (`app/Providers/NativeServiceProvider.php:37-45`, same `notifications_resynced_at` cache guard) switches from per-action rescheduling to `PlanScheduler::rescheduleAll()` — cancel+reschedule with freshly computed `at` on every app open (5-min TTL).

**Observers:** `BabyActionObserver` drops its `LocalNotificationScheduler` dependency entirely; injects `PlanScheduler`, calls `reanchorForAction` in created/updated (same changed-fields check)/deleted. `BabyObserver` attaches new babies to all-children plans + reschedules them.

## Timezones

DB stays pure UTC; only the webview knows the device offset. The calendar page's root carries `x-init="$wire.setDeviceTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"`; the component validates against `timezone_identifiers_list()`, uses it for all day/month boundaries, stamps it on plans at save, and updates+reschedules any plan whose stored tz differs (heals travel/DST whenever the page opens). Until the init round-trip lands, mount defaults to UTC — corrected invisibly on first update.

## Calendar page `/planning` (Schedule-X)

New `app/Livewire/Pages/Planning/Index.php` + `resources/views/livewire/pages/planning/index.blade.php`.

**Dependency (user-approved):** `npm install @schedule-x/calendar @schedule-x/theme-default` (+ events-service/current-time plugin packages per the installed version's split). Bundled via Vite (offline app — no CDN). **First implementation step: verify exact Schedule-X API via Context7** (event shape, callbacks, view config) — don't code it from memory.

- Blade: `<div wire:ignore x-data x-init="window.initPlanningCalendar($wire, $el)">`; new `resources/js/planning-calendar.js` (imported in `app.js`, theme CSS alongside) creates the calendar with month-grid + day views (Schedule-X's header gives the Month ⇄ Day switch and prev/Today/next), `calendars` config coloring events per action type plus a muted "planned" calendar.
- Callbacks: month date click → day view on that date; event click → logged actions `Livewire.navigate()` to `baby_actions.edit`, planned slots `$wire.openEdit(planId)`; visible-range change → `$wire.getEvents(from, to)` refreshes via the events-service plugin. Destroy/re-init on `livewire:navigated` (same concern the drawer-swipe IIFE handles).
- `getEvents(string $from, string $to): array` (server-side, local times in `device_timezone`): logged duration actions as blocks (ongoing → grows to now, 60s JS re-fetch), instant actions as ~15-min tappable blocks, planned slots as status-prefixed events (✓ done / ✗ missed / next).
- **Baby filter chips** (daisyUI) when >1 baby; toggling refreshes events.

**Slot projection & matching** — plain class `app/Services/PlanTimeline.php` (pure function of plans+actions+date+tz, unit-testable):
- Clock-based plans (Days/Weeks/Months units) expand to concrete slots on their scheduled local days/times (with `duration_minutes` rendering as a block when set).
- Hours-unit plans: chained projection — anchor = last matching action before the day window (or day start); each logged action resets the chain; slots beyond the next render as lighter estimates. Projection only for today-forward; past days show fixed-slot done/missed + actual actions.
- Matching: done when a logged action's reference time is within ±`SLOT_MATCH_TOLERANCE_MINUTES = 45` of the slot (nearest-first, one slot per action); missed when slot+tolerance passed unmatched; else upcoming (soonest = next).

## Plan editor (modal on `/planning`, NotificationSettings-style conventions)

Flat public props, `toggle*` methods, `x-mary-modal` with the scoped-style close-X trick, hand-rolled `z-40` delete-confirm overlay opened after `showModal = false`:
- Segmented buttons: action type, **repeat unit** (Hours / Days / Weeks / Months — the `interval_unit` selection), child targeting; unit toggle clears fields that don't apply.
- **"Repeat every [N] [unit]"** control: one numeric input whose value is `interval_minutes` converted to the selected unit for display, converted back to minutes on save (`Plan::intervalValue()` / `setIntervalFromValue()` accessor pair — one conversion helper, unit-tested). Per-unit extras: notify-from (Hours); `times` list of `<input type="time">` rows with add/remove (Days/Weeks/Months); weekday toggle chips (Weeks); anchor date (multi-day/week cadences, day-of-month for Months). Optional duration.
- **Notifications attachment UI**: two chip rows — "Notify at start" and "Notify at end" — listing the message library as toggle chips, plus an inline "new message" title/description mini-form (pattern: the medication tag picker). End row disabled until a duration is set (non-interval kinds). Attaching/detaching syncs the pivot with `trigger`.
- Validation per kind; `savePlan` persists, syncs `babies` + `notifications` pivots, stamps tz, then `rescheduleFor`. `deletePlan` cancels first. Enable toggle on each plan row in a "Manage plans" section above the calendar (calendar stays the hero).

## Notification messages page `/notifications`

Tiny CRUD replacing the old settings page: `app/Livewire/Pages/Notification/Index.php` + blade, card-list pattern like Medications — list messages (title + description), add/edit inline modal, delete detaches from plans (pivot cascade) and reschedules affected plans. Keeps the message library manageable without bloating the plan editor.

## Routes & navigation

- `routes/web.php`: remove `/notification-settings`; add `Route::get('/planning', ...)->name('planning.show')` and `Route::get('/notifications', ...)->name('notifications.show')`.
- `resources/views/layouts/app.blade.php:31-35`: menu becomes Dashboard / **Planning** (`o-calendar-days`) / Babies / Baby Actions / Medications / **Notifications** (`o-bell`, now the messages page). The permission banner (dashboard) keeps working — `HandlesNotificationPermission` is untouched; also mount it on the Planning page since that's now the notification hub.

## Testing

- `tests/Feature/PlanSchedulerTest.php` — Mockery partial-mock payload capture (pattern from the old scheduler test): per-unit payloads (`repeat`/`repeatDays`/`repeatIntervalSeconds`, correct UTC `at` for Europe/Athens incl. a DST boundary); interval anchoring + observer re-anchor on create/update/delete; start vs end triggers (incl. end-skip when last action unfinished); none attached → nothing scheduled; key formats; cancel on disable/delete; child targeting.
- `tests/Feature/PlanTimelineTest.php` — expansion per unit, hourly-interval chaining, ±45 min matching, statuses, tz day boundaries.
- `tests/Feature/PlanManagementTest.php` — `Livewire::test(Planning\Index::class)`: CRUD, per-unit validation, **minutes ↔ display-unit conversion round-trip** (save "3 hours" → 180 stored → editor shows 3/hours; "3 days" → 4320), notification attach/detach with triggers, inline message creation, `setDeviceTimezone` healing, pivot sync, `BabyObserver` all-children attach.
- `tests/Feature/PlanningCalendarTest.php` — `getEvents` payload contract (shapes, local times, statuses, baby filter, 23:30-local lands on the right date).
- `tests/Feature/NotificationMessagesTest.php` — messages CRUD + delete-detaches-and-reschedules.
- Update `MedicationManagementTest` for removed rule guards. Run `./vendor/bin/pint --dirty --format agent` + `php artisan test --compact` at the end.

## Build order

1. New tables migration (`notifications`, `plans`, `notification_plan`, `baby_plan`) + enums + models + factories.
2. Demolition migration (drop rule tables + `baby_actions` bookkeeping columns).
3. `PlanScheduler` (absorbs seams + `SOUND_NAME`); rewire `BabyActionObserver`/`BabyObserver`/`NativeServiceProvider`; delete `LocalNotificationScheduler` + old models.
4. Delete NotificationSettings page/route/menu + old tests; strip rule guards from Medication/Baby pages.
5. `PlanTimeline` class.
6. Verify Schedule-X via Context7; `npm install`; `resources/js/planning-calendar.js` + `app.js`/CSS imports.
7. `Planning\Index` (calendar, `getEvents`, filter chips, plan editor modal, delete overlay, tz init) + `Notification\Index` messages page.
8. Routes + menu; `npm run build` (Vite manifest).
9. Tests alongside each step; update CLAUDE.md's notification-system sections to describe the new architecture; Pint.

## Non-goals / later

- Quiet hours / do-not-disturb windows for interval plans.
- Reminder offsets ("10 min before slot").
- Dashboard "next planned" widget.
- Any migration of old rules (dropped, per user decision).

## Risks / verification

- Schedule-X exact API + webview ergonomics — verify via Context7 before coding; FullCalendar is the fallback with the same integration pattern.
- Plugin `repeat`/`repeatDays`/`repeatIntervalSeconds` semantics and `soundName`-with-repeat coexistence need on-device verification (user runs `php artisan native:run android` themselves).
- `repeatIntervalSeconds` (EveryNDays) drifts across DST until the next app-open resync — accepted.
- Dropping the rule tables is destructive and irreversible on-device once shipped — called out deliberately; the user asked for exactly this.
