<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vrefos') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 nativephp-safe-area">

    {{-- NAVBAR --}}
    <x-mary-nav sticky full-width>
        <x-slot:brand class="relative justify-center">
            <label for="main-drawer" class="lg:hidden absolute left-0 top-1/2 -translate-y-1/2">
                <x-mary-icon name="o-bars-3" class="cursor-pointer" />
            </label>
            <picture>
                <source srcset="{{ asset('vrefos-logo-dark.svg') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('vrefos-logo.svg') }}" alt="{{ config('app.name', 'Vrefos') }}" class="h-8 w-auto">
            </picture>
        </x-slot:brand>
    </x-mary-nav>

    {{-- MAIN --}}
    <x-mary-main with-nav full-width>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">
            <x-mary-menu activate-by-route style="padding-top: env(safe-area-inset-top)">
                <x-mary-menu-item title="Dashboard" icon="o-home" link="{{ route('dashboard') }}" exact />
                <x-mary-menu-item title="Babies" icon="o-cake" link="{{ route('babies.show') }}" />
                <x-mary-menu-item title="Baby Actions" icon="o-clock" link="{{ route('baby_actions.show') }}" />
                <x-mary-menu-item title="Medications" icon="o-beaker" link="{{ route('medications.show') }}" />
                <x-mary-menu-item title="Notifications" icon="o-bell" link="{{ route('notification-settings.edit') }}" />
            </x-mary-menu>
        </x-slot:sidebar>

        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    <x-mary-toast />

    @livewireScripts
</body>
</html>
