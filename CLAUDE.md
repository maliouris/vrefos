# Vrefos — CLAUDE.md

## Project Overview

Vrefos is a Laravel 13 web application for parents to track toddler/baby activities (feeding, sleeping, etc.). It sends push notifications reminding parents when it's time for the next feeding or sleep session.

## Tech Stack

- **Backend:** PHP 8.2+, Laravel 13, Laravel Sanctum, Laravel Sail (Docker)
- **Frontend:** Livewire v4, Tailwind CSS v4, daisyUI v5, MaryUI v2 (component prefix: `x-mary-`)
- **Database:** MySQL 8.0 (via Sail)
- **Push Notifications:** Pusher Beams (`pusher/pusher-push-notifications`, `@pusher/push-notifications-web`)
- **Dev tools:** Vite + `@tailwindcss/vite`, Laravel Pint (code style), fumeapp/modeltyper (TS types from models)

## Running the Project

```bash
./vendor/bin/sail up -d          # Start Docker containers
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev    # Vite dev server
./vendor/bin/sail npm run build  # Production build
```

## Key Commands

```bash
./vendor/bin/sail artisan app:send-baby-action-reminder   # Send pending reminders
./vendor/bin/sail artisan tinker
./vendor/bin/sail composer ...                             # Run any composer command
./vendor/bin/sail npm ...                                  # Run any npm command
./vendor/bin/sail pint                                     # Format PHP code
```

> **IMPORTANT:** Always use `./vendor/bin/sail composer` and `./vendor/bin/sail npm` instead of running `composer` or `npm` directly. Never invoke `composer` or `npm` without the sail prefix.

## Architecture

### Backend

- `app/Models/` — `User`, `Baby`, `BabyAction`, `BabyActionType`, `NotificationSetting`
- `app/Policies/BabyPolicy.php` — Authorizes `update` only when the authenticated user owns the baby (`user_id` match). Auto-discovered by Laravel.
- `app/Policies/BabyActionPolicy.php` — Authorizes `update` only when the authenticated user owns the action's parent baby. Auto-discovered by Laravel.
- `app/Services/BabyActionsService.php` — Sends reminders via push notifications
- `app/Services/BeamsNotificationsService.php` — Pusher Beams implementation of `PushNotifications` contract
- `app/Services/BeamsClientService.php` — Pusher Beams SDK client wrapper
- `app/Contracts/PushNotifications.php` — Interface for push notification backends
- `app/Console/Commands/SendBabyActionsReminders.php` — Scheduled command: for each unreminded finished action, looks up the user's `NotificationSetting` for that action type; skips if disabled or threshold not reached; increments `reminders` after sending
- `app/Enums/Gender.php` — `male` / `female`

### Data Model

```
User → hasMany → Baby → hasMany → BabyAction → belongsTo → BabyActionType
User → hasMany → NotificationSetting → belongsTo → BabyActionType
```

`BabyAction` fields: `baby_id`, `baby_action_type_id`, `started_at`, `finished_at`, `reminders` (int, default 0)

`NotificationSetting` fields: `user_id`, `baby_action_type_id`, `enabled` (bool, default true), `notify_after_minutes` (int, default 180). Unique on `(user_id, baby_action_type_id)`. Created automatically via `firstOrCreate` on first page visit or first reminder run.

### Frontend

- `app/Livewire/Pages/` — Full-page Livewire components (registered via `Route::get('/uri', ComponentClass::class)`)
- `resources/views/livewire/pages/` — Blade views for Livewire page components
- `resources/views/layouts/app.blade.php` — Authenticated layout (MaryUI `x-mary-main`, sidebar, navbar)
- `resources/views/components/guest-layout.blade.php` — Guest layout (MaryUI `x-mary-card`, centered)
- `resources/views/auth/` — Auth pages (plain Blade + MaryUI, handled by controllers)
- `resources/js/app.js` — Minimal JS: Pusher Beams push notification registration only

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
| GET | `/babies` | `babies.show` | `Pages\Baby\Index` |
| GET | `/babies/add` | `babies.create` | `Pages\Baby\Create` |
| GET | `/babies/{baby}/edit` | `babies.edit` | `Pages\Baby\Edit` |
| GET | `/baby_actions` | `baby_actions.show` | `Pages\BabyAction\Index` |
| GET | `/baby_actions/add` | `baby_actions.create` | `Pages\BabyAction\Create` |
| GET | `/baby_actions/{babyAction}/edit` | `baby_actions.edit` | `Pages\BabyAction\Edit` |
| GET | `/profile` | `profile.edit` | `Pages\Profile\Edit` |
| GET | `/notification-settings` | `notification-settings.edit` | `Pages\NotificationSettings\Index` |
| GET | `/pusher/beams-auth` | `pusher.beams.auth` | — |

Root `/` redirects to `/babies`.

## Notification System

Reminders are sent via Pusher Beams push notifications:

1. The artisan command `app:send-baby-action-reminder` runs every minute (scheduled in `routes/console.php`).
2. It fetches all finished `baby_actions` with `reminders < 1`, eager-loading `baby.user` and `babyActionType`.
3. For each action it calls `NotificationSetting::firstOrCreate()` with the action's user + action type — defaults to `enabled=true`, `notify_after_minutes=180` (3 hours).
4. Skips if `enabled = false` or elapsed minutes since `finished_at` < `notify_after_minutes`.
5. `BabyActionsService::sendReminder()` dispatches the push notification and increments `reminders`.
6. The `PushNotifications` contract is implemented by `BeamsNotificationsService`, injected via the service container.
7. The frontend registers with Beams via `window.registerPushNotifications()` in `resources/js/app.js`.

Users configure their notification preferences at `/notification-settings` (one setting per action type: Eat, Sleep).

## Testing

PHPUnit 12 — run with:

```bash
./vendor/bin/sail artisan test
```

Test database is created automatically by Sail's MySQL init script.

## Code Style

PHP: Laravel Pint (run `./vendor/bin/sail pint` before committing).

## Documentation Lookup

When looking up docs for any library, framework, SDK, API, or CLI tool, always use **Context7 MCP** first (`resolve-library-id` → `query-docs`). Do not fall back to web search without explicitly asking the user for permission first.
