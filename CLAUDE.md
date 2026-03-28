# Vrefos — CLAUDE.md

## Project Overview

Vrefos is a Laravel 13 web application for parents to track toddler/baby activities (feeding, sleeping, etc.). It sends push notifications reminding parents when it's time for the next feeding or sleep session.

## Tech Stack

- **Backend:** PHP 8.2+, Laravel 13, Laravel Sanctum, Laravel Sail (Docker)
- **Frontend:** Vue 3 (Composition API), TypeScript, Inertia v2 (SSR enabled), Tailwind CSS v3
- **Database:** MySQL 8.0 (via Sail)
- **Push Notifications:** Pusher Beams (`pusher/pusher-push-notifications`, `@pusher/push-notifications-web`)
- **UI:** Radix Vue, shadcn-style components, Lucide icons, TanStack Table
- **Forms:** vee-validate + Zod
- **Routing:** Ziggy (Laravel routes in JS)
- **Dev tools:** Vite, vue-tsc, Laravel Pint (code style), fumeapp/modeltyper (TS types from models)

## Running the Project

```bash
./vendor/bin/sail up -d          # Start Docker containers
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev    # Vite dev server
./vendor/bin/sail npm run build  # Production build (also runs vue-tsc + SSR build)
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

- `app/Models/` — `User`, `Baby`, `BabyAction`, `BabyActionType`
- `app/Services/BabyActionsService.php` — Sends reminders via push notifications
- `app/Services/BeamsNotificationsService.php` — Pusher Beams implementation of `PushNotifications` contract
- `app/Services/BeamsClientService.php` — Pusher Beams SDK client wrapper
- `app/Contracts/PushNotifications.php` — Interface for push notification backends
- `app/Console/Commands/SendBabyActionsReminders.php` — Scheduled command: fires when `finished_at` > 2h45m ago and `reminders < 1`
- `app/Enums/Gender.php` — `male` / `female`

### Data Model

```
User → hasMany → Baby → hasMany → BabyAction → belongsTo → BabyActionType
```

`BabyAction` fields: `baby_id`, `baby_action_type_id`, `started_at`, `finished_at`, `reminders` (int, default 0)

### Frontend

- `resources/js/app/Pages/` — Inertia page components (Vue SFC)
- `resources/js/app/services/` — `beams-notification-service.ts`, `push-notifications-service.ts`
- `resources/js/pusher/client.ts` — Pusher Beams client initialisation
- `resources/js/app/components/` & `Components/` — Reusable Vue components
- `resources/js/app/Layouts/` — App layouts
- SSR entry: `resources/js/app/ssr.ts`

### Routes (web.php)

| Method | URI | Name |
|--------|-----|-------|
| GET | `/babies` | `babies.show` |
| GET | `/babies/add` | `babies.create` |
| POST | `/babies` | `babies.store` |
| GET | `/babies/{baby}/edit` | `babies.edit` |
| PATCH | `/babies/{baby}/update` | `babies.update` |
| GET | `/baby_actions` | `baby_actions.show` |
| GET | `/baby_actions/add` | `baby_actions.create` |
| POST | `/baby_actions` | `baby_actions.store` |
| GET | `/baby_actions/{babyAction}/edit` | `baby_actions.edit` |
| PATCH | `/baby_actions/{babyAction}/update` | `baby_actions.update` |
| GET | `/pusher/beams-auth` | `pusher.beams.auth` |

Dashboard redirects to `babies.show`.

## Notification System

Reminders are sent via Pusher Beams push notifications:

1. The artisan command `app:send-baby-action-reminder` queries `baby_actions` where `finished_at < now() - 2h45m` and `reminders < 1`.
2. `BabyActionsService::sendReminder()` dispatches a push notification to the baby's parent and increments `reminders`.
3. The `PushNotifications` contract is implemented by `BeamsNotificationsService`, injected via the service container.
4. The frontend authenticates with Beams at `/pusher/beams-auth`.

Schedule this command via Laravel's scheduler or a cron job to run every minute.

## Testing

PHPUnit 12 — run with:

```bash
./vendor/bin/sail artisan test
```

Test database is created automatically by Sail's MySQL init script.

## Code Style

PHP: Laravel Pint (run `./vendor/bin/sail pint` before committing).
TypeScript: vue-tsc enforces type checking during build.
