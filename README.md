# Vrefos

A Laravel 13 + NativePHP Mobile app for parents to track toddler/baby activities (feeding, sleeping, etc.) and receive local notifications when it's time for the next session.

## Tech Stack

- **Backend:** PHP 8.5, Laravel 13, NativePHP Mobile v3
- **Frontend:** Livewire v4, Tailwind CSS v4, daisyUI v5, MaryUI v2
- **Database:** SQLite (on-device via NativePHP), MySQL 8.0 via Docker for local dev
- **Notifications:** On-device local notifications (`ikromjon/nativephp-mobile-local-notifications`)
- **Dev tools:** Vite, Laravel Pint, mise (PHP 8.5 + Node 24)

## Running the Project

```bash
./vendor/bin/sail up -d   # Start MySQL Docker container
php artisan migrate
npm run dev               # Vite dev server
```

> **Note:** Sail manages only the MySQL container. PHP, Composer, and Node run directly via mise. Never use `sail artisan`, `sail composer`, or `sail npm`.

## Building for Android

```bash
npm run build -- --mode=android
php artisan native:run android
```

## Key Commands

```bash
php artisan migrate          # Run database migrations
php artisan test --compact   # Run tests
./vendor/bin/pint            # Format PHP code
php artisan native:watch     # Hot reload during development
php artisan native:tail      # Stream device logs
```

## Architecture

### Notification System

Reminders are delivered as **on-device local notifications** — no server, no internet required.

1. When a `BabyAction` is saved, `BabyActionObserver` triggers `LocalNotificationScheduler`.
2. The scheduler calculates `fire_at = reference_time + notify_after_minutes` based on the user's `NotificationSetting`.
3. Skips silently if: notifications disabled, reference time is null, or `fire_at` is already in the past.
4. The OS delivers the notification at `fire_at`; tapping it opens the action's edit page.
5. On app boot, `NativeServiceProvider` resyncs all pending notifications (guards against OS clearing alarms on reboot).

### Data Model

```
Baby → hasMany → BabyAction → belongsTo → BabyActionType
                 BabyAction → hasOne    → BabyActionEatDetail
NotificationSetting → belongsTo → BabyActionType
```

`BabyAction` fields: `baby_id`, `baby_action_type_id`, `started_at`, `finished_at`, `notification_scheduled_at`

`NotificationSetting` fields: `baby_action_type_id`, `enabled`, `notify_after_minutes`, `notify_from` (`started_at` / `finished_at`)

### Key Files

| File | Purpose |
|------|---------|
| `app/Services/LocalNotificationScheduler.php` | Schedules/cancels/reschedules OS notifications |
| `app/Observers/BabyActionObserver.php` | Auto-triggers scheduler on model events |
| `app/Livewire/Pages/NotificationSettings/Index.php` | User notification preferences + cascade |
| `app/Providers/NativeServiceProvider.php` | Requests permission, resyncs on boot |

## Testing

```bash
php artisan test --compact
```

Tests use `LocalNotifications::fake()` so they run without a device.
