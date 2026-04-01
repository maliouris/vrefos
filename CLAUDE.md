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

- `app/Models/` — `User`, `Baby`, `BabyAction`, `BabyActionEatDetail`, `BabyActionType`, `NotificationSetting`
- `app/Policies/BabyPolicy.php` — Authorizes `update` only when the authenticated user owns the baby (`user_id` match). Auto-discovered by Laravel.
- `app/Policies/BabyActionPolicy.php` — Authorizes `update` only when the authenticated user owns the action's parent baby. Auto-discovered by Laravel.
- `app/Services/BabyActionsService.php` — Sends reminders via push notifications
- `app/Services/BeamsNotificationsService.php` — Pusher Beams implementation of `PushNotifications` contract
- `app/Services/BeamsClientService.php` — Pusher Beams SDK client wrapper
- `app/Contracts/PushNotifications.php` — Interface for push notification backends
- `app/Console/Commands/SendBabyActionsReminders.php` — Scheduled command: for each unreminded action, looks up the user's `NotificationSetting` for that action type; skips if disabled, reference time is null, or threshold not reached; increments `reminders` after sending
- `app/Enums/Gender.php` — `male` / `female`
- `app/Enums/FoodType.php` — `breast_milk`, `formula`, `fruits`, `vegetables`, `grains`, `protein`, `dairy`, `other`
- `app/Enums/BreastSide.php` — `left` / `right`
- `app/Enums/NotifyFrom.php` — `started_at` / `finished_at`

### Data Model

```
User → hasMany → Baby → hasMany → BabyAction → belongsTo → BabyActionType
                                  BabyAction  → hasOne    → BabyActionEatDetail
User → hasMany → NotificationSetting → belongsTo → BabyActionType
```

`BabyAction` fields: `baby_id`, `baby_action_type_id`, `started_at`, `finished_at`, `reminders` (int, default 0)

`BabyActionEatDetail` fields: `baby_action_id`, `food_type` (nullable, cast to `FoodType` enum), `breast_side` (nullable, cast to `BreastSide` enum). One-to-one with `BabyAction`; cascade-deleted with parent. Only created when action type is "Eat" and a food type is selected.

`NotificationSetting` fields: `user_id`, `baby_action_type_id`, `enabled` (bool, default true), `notify_after_minutes` (int, default 180), `notify_from` (cast to `NotifyFrom` enum, default `started_at`). Unique on `(user_id, baby_action_type_id)`. Created automatically via `firstOrCreate` on first page visit or first reminder run.

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
2. It fetches all `baby_actions` with `reminders < 1`, eager-loading `baby.user` and `babyActionType`.
3. For each action it calls `NotificationSetting::firstOrCreate()` with the action's user + action type — defaults to `enabled=true`, `notify_after_minutes=180` (3 hours), `notify_from=started_at`.
4. Skips if `enabled = false`.
5. Determines the reference time: `started_at` or `finished_at` based on `notify_from`. Skips if the reference time is null.
6. Skips if elapsed minutes since the reference time < `notify_after_minutes`.
7. `BabyActionsService::sendReminder()` dispatches the push notification and increments `reminders`.
8. The `PushNotifications` contract is implemented by `BeamsNotificationsService`, injected via the service container.
9. The frontend registers with Beams via `window.registerPushNotifications()` in `resources/js/app.js`.

Users configure their notification preferences at `/notification-settings` (one setting per action type: Eat, Sleep). Each setting includes: enabled toggle, notify-from selector (start time / end time), and notify-after-minutes input.

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- livewire/volt (VOLT) - v1
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `livewire-development` — Use for any task or question involving Livewire. Activate if user mentions Livewire, wire: directives, or Livewire-specific concepts like wire:model, wire:click, wire:sort, or islands, invoke this skill. Covers building new components, debugging reactivity issues, real-time form validation, drag-and-drop, loading states, migrating from Livewire 3 to 4, converting component formats (SFC/MFC/class-based), and performance optimization. Do not use for non-Livewire reactive UI (React, Vue, Alpine-only, Inertia.js) or standard Laravel forms without Livewire.
- `volt-development` — Develops single-file Livewire components with Volt. Activates when creating Volt components, converting Livewire to Volt, working with @volt directive, functional or class-based Volt APIs; or when the user mentions Volt, single-file components, functional Livewire, or inline component logic in Blade files.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

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

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

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

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

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

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
