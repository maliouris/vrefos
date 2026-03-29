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
<body class="font-sans antialiased">
    <x-mary-main full-width>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">
            <x-mary-menu activate-by-route>
                <x-mary-menu-item title="Babies" icon="o-cake" link="{{ route('babies.show') }}" />
                <x-mary-menu-item title="Baby Actions" icon="o-clock" link="{{ route('baby_actions.show') }}" />
            </x-mary-menu>
        </x-slot:sidebar>

        <x-slot:navbar>
            <x-mary-button icon="o-bars-3" responsive drawer="main-drawer" class="btn-ghost" />
            <div class="flex-1 font-bold text-lg">{{ config('app.name', 'Vrefos') }}</div>
            @auth
                <span class="text-sm mr-2">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-mary-button type="submit" label="Logout" icon="o-arrow-right-on-rectangle" class="btn-ghost btn-sm" />
                </form>
            @endauth
        </x-slot:navbar>

        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    <x-mary-toast />
    @livewireScripts
</body>
</html>
