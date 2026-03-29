# Replace Inertia.js with Livewire v4 + MaryUI + Tailwind

## Context
The project is a Laravel app (vrefos) currently built as an Inertia.js + Vue 3 SPA with shadcn-style UI components.
The goal is to replace the entire frontend with a server-driven Livewire v4 stack, using MaryUI (built on daisyUI + Tailwind CSS v4) for UI components and creating custom Blade components only when MaryUI doesn't cover the need.

---

## Phase 1 — Dependencies

### composer.json
Remove:
- `inertiajs/inertia-laravel`
- `tightenco/ziggy`

Add:
- `livewire/livewire`
- `robsontenorio/mary`

Commands:
```bash
sail composer remove inertiajs/inertia-laravel tightenco/ziggy
sail composer require livewire/livewire robsontenorio/mary
sail php artisan mary:install
```

### package.json
Remove everything Vue/Inertia/shadcn-related:
- `@inertiajs/vue3`, `vue`, `@vue/server-renderer`, `vue-tsc`
- `@vitejs/plugin-vue`
- `@tanstack/vue-table`, `radix-vue`, `@vueuse/core`
- `vee-validate`, `@vee-validate/zod`, `zod`
- `class-variance-authority`, `clsx`, `tailwind-merge`, `tailwindcss-animate`
- `lucide-vue-next`
- `autoprefixer`, `postcss`
- `tailwindcss` (v3 — will reinstall as v4)

Keep / add:
- `vite`, `laravel-vite-plugin`
- `tailwindcss` (v4), `@tailwindcss/vite`
- `daisyui` (pulled in by MaryUI install)
- `@pusher/push-notifications-web` (still needed for push notifications)

### vite.config.js (replaces vite.config.ts)
```js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] }),
        tailwindcss(),
    ],
})
```

### resources/css/app.css (Tailwind v4 format)
```css
@import "tailwindcss";

@plugin "daisyui" {
    themes: light --default, dark --prefersdark;
}

@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php";
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php";
@source "../../storage/framework/views/*.php";
@source "../**/*.blade.php";
@source "../**/*.js";
```

### resources/js/app.js (minimal — push notifications only)
```js
import * as PusherPushNotifications from '@pusher/push-notifications-web'

window.registerPushNotifications = async (userId) => {
    const beamsClient = new PusherPushNotifications.Client({ instanceId: '...' })
    await beamsClient.start()
    await beamsClient.addDeviceInterest(`user-${userId}`)
}
```

---

## Phase 2 — Blade Layouts

### resources/views/layouts/app.blade.php
Full MaryUI layout using `x-mary-nav`, `x-mary-main`, `x-mary-menu`, `x-mary-menu-item`:
- Sticky navbar with app name and user greeting + logout button
- Collapsible sidebar with drawer support (mobile-friendly)
- Menu items: Babies (`/babies`), Baby Actions (`/baby_actions`)
- `@livewireStyles` in `<head>`, `@livewireScripts` before `</body>`
- `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- `x-mary-toast` for flash messages

### resources/views/layouts/guest.blade.php
Centered card layout for auth pages:
- App logo / name at top
- Centered container with MaryUI `x-mary-card`
- Used by all auth pages

---

## Phase 3 — Auth Pages (Blade, not Livewire)

Standard Blade forms — auth controllers handle validation/redirects via Laravel, no Livewire needed.

Files to create:
- `resources/views/auth/login.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/confirm-password.blade.php`

Update these controllers to return `view('auth.*')` instead of `Inertia::render()`:
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Auth/ConfirmablePasswordController.php`

---

## Phase 4 — Livewire Full-Page Components

Each Vue page becomes a full-page Livewire component registered via `Route::livewire()`.
Namespace: `App\Livewire\Pages\` → component syntax `pages::baby.index`.
Data fetching in `mount()`. Forms use `wire:model` + Laravel validation.

### Baby

**`app/Livewire/Pages/Baby/Index.php`** → `pages::baby.index`
- `mount()`: `$babies = auth()->user()->babies`
- Blade: `x-mary-table` with headers `[name, birth_date]` + edit action link

**`app/Livewire/Pages/Baby/Create.php`** → `pages::baby.create`
- Properties: `$name`, `$birth_date`
- `save()`: validate → `Baby::create()` → redirect to list with flash
- Blade: `x-mary-form wire:submit="save"` with `x-mary-input`, `x-mary-datepicker`, `x-mary-button`

**`app/Livewire/Pages/Baby/Edit.php`** → `pages::baby.edit`
- `mount(Baby $baby)`: populate `$name`, `$birth_date` from model
- `update()`: validate → update model → flash success (stay on page)
- Blade: same structure as Create

Blade views: `resources/views/livewire/pages/baby/{index,create,edit}.blade.php`

### BabyAction

**`app/Livewire/Pages/BabyAction/Index.php`** → `pages::baby-action.index`
- `mount()`: eager-load `BabyAction::with(['baby','babyActionType'])->whereHas('baby', ...)`
- Blade: `x-mary-table` with `[baby.name, babyActionType.name, started_at, finished_at]`; use `['format' => ['date', 'd/m/Y H:i']]` in headers

**`app/Livewire/Pages/BabyAction/Create.php`** → `pages::baby-action.create`
- Properties: `$baby_id`, `$baby_action_type_id`, `$started_at`, `$finished_at`
- `mount()`: load `$babies`, `$actionTypes` for selects
- `save()`: validate → create → redirect with flash
- Blade: `x-mary-select` for baby and type, `x-mary-datetime type="datetime-local"` for timestamps

**`app/Livewire/Pages/BabyAction/Edit.php`** → `pages::baby-action.edit`
- `mount(BabyAction $babyAction)`: authorize + populate properties
- `update()`: validate → update → flash success

Blade views: `resources/views/livewire/pages/baby-action/{index,create,edit}.blade.php`

### Profile

**`app/Livewire/Pages/Profile/Edit.php`** → `pages::profile.edit`
- Methods: `updateProfile()`, `updatePassword()`, `deleteAccount()`
- Reuse `app/Http/Requests/Auth/ProfileUpdateRequest.php` validation rules
- Blade: three `x-mary-card` sections, each with its own `x-mary-form`

Blade view: `resources/views/livewire/pages/profile/edit.blade.php`

### Legal

Simple Blade view (no Livewire): `resources/views/legal/terms-and-conditions.blade.php`

---

## Phase 5 — Update Routes

`routes/web.php`:
```php
Route::get('/', fn() => redirect('/babies'));

Route::middleware('auth')->group(function () {
    Route::livewire('/babies',              'pages::baby.index')->name('babies.show');
    Route::livewire('/babies/add',          'pages::baby.create')->name('babies.create');
    Route::livewire('/babies/{baby}/edit',  'pages::baby.edit')->name('babies.edit');

    Route::livewire('/baby_actions',                    'pages::baby-action.index')->name('baby_actions.show');
    Route::livewire('/baby_actions/add',                'pages::baby-action.create')->name('baby_actions.create');
    Route::livewire('/baby_actions/{babyAction}/edit',  'pages::baby-action.edit')->name('baby_actions.edit');

    Route::livewire('/profile', 'pages::profile.edit')->name('profile.edit');
});

Route::get('/terms-and-conditions', fn() => view('legal.terms-and-conditions'));
```

Remove all `POST` and `PATCH` routes for Baby/BabyAction — form submission is handled internally by Livewire.

`routes/auth.php` — no structural changes needed.

---

## Phase 6 — Middleware Cleanup

`bootstrap/app.php`: remove `HandleInertiaRequests` from middleware stack.

Delete: `app/Http/Middleware/HandleInertiaRequests.php`

---

## Phase 7 — Delete Obsolete Files

```
resources/views/app.blade.php
resources/js/app/
tailwind.config.js
postcss.config.js
tsconfig.json
vite.config.ts
components.json
app/Http/Controllers/BabyController.php
app/Http/Controllers/BabyActionController.php
app/Http/Controllers/ProfileController.php
```

---

## Custom Blade Components

Create under `resources/views/components/` only when MaryUI doesn't cover the need:
- `app-logo.blade.php` — application logo

---

## MaryUI Component Mapping

| Current (Vue/shadcn)  | Replacement (MaryUI)                                    |
|-----------------------|---------------------------------------------------------|
| DataTable (TanStack)  | `<x-mary-table>`                                        |
| Button                | `<x-mary-button>`                                       |
| Input                 | `<x-mary-input>`                                        |
| Select                | `<x-mary-select>`                                       |
| Datepicker            | `<x-mary-datepicker>`                                   |
| Datetime picker       | `<x-mary-datetime type="datetime-local">`               |
| Card                  | `<x-mary-card>`                                         |
| ParentPanelLayout     | `<x-mary-nav>` + `<x-mary-main>` + `<x-mary-menu>`     |
| GuestLayout           | `guest.blade.php` with `<x-mary-card>`                  |
| Toast/Flash           | `<x-mary-toast>`                                        |

---

## Verification

1. `sail up -d` — containers running
2. `sail npm run dev` — Vite compiles without errors
3. `sail php artisan route:list` — all routes resolve correctly
4. `/login` — guest layout renders, form submits correctly
5. `/babies` — sidebar layout renders, table shows data
6. `/babies/add` — form validates, saves, redirects with flash
7. `/babies/{id}/edit` — pre-filled form saves correctly
8. Repeat for baby actions and profile
9. Mobile viewport — sidebar collapses to drawer
10. Push notifications — register on login, stop on logout
