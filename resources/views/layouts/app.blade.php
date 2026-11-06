<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'Vrefos') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen font-sans antialiased bg-base-200 nativephp-safe-area">

    {{-- NAVBAR --}}
    <x-mary-nav sticky full-width>
        <x-slot:brand class="relative justify-center">
            @php
                $topLevelRoutes = ['dashboard', 'baby_actions.show', 'planning.show', 'medications.show', 'notifications.show', 'babies.show'];
                $isDetailPage = ! request()->routeIs($topLevelRoutes);
            @endphp
            @if ($isDetailPage)
                <button
                    type="button"
                    aria-label="Back"
                    class="lg:hidden absolute left-0 top-1/2 -translate-y-1/2 p-2 -ml-2"
                    @click="history.length > 1 ? history.back() : Livewire.navigate('{{ route('dashboard') }}')"
                >
                    <x-mary-icon name="o-arrow-left" />
                </button>
            @else
                <label for="main-drawer" role="button" aria-label="Open menu" class="lg:hidden absolute left-0 top-1/2 -translate-y-1/2 p-2 -ml-2">
                    <x-mary-icon name="o-bars-3" class="cursor-pointer" />
                </label>
            @endif
            <a href="{{ route('dashboard') }}" wire:navigate aria-label="Home">
                <picture>
                    <source srcset="{{ asset('vrefos-logo-dark.svg') }}" media="(prefers-color-scheme: dark)">
                    <img src="{{ asset('vrefos-logo.svg') }}" alt="{{ config('app.name', 'Vrefos') }}" class="h-8 w-auto">
                </picture>
            </a>
        </x-slot:brand>
    </x-mary-nav>

    {{-- MAIN --}}
    <x-mary-main with-nav full-width>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">
            <x-mary-menu activate-by-route class="pt-2">
                <x-mary-menu-item title="Dashboard" icon="o-home" link="{{ route('dashboard') }}" exact />
                <x-mary-menu-item title="Planning" icon="o-calendar-days" link="{{ route('planning.show') }}" />
                <x-mary-menu-item title="Babies" icon="o-cake" link="{{ route('babies.show') }}" />
                <x-mary-menu-item title="Diary" icon="o-clock" link="{{ route('baby_actions.show') }}" />
                <x-mary-menu-item title="Medications" icon="o-beaker" link="{{ route('medications.show') }}" />
                <x-mary-menu-item title="Notifications" icon="o-bell" link="{{ route('notifications.show') }}" />
            </x-mary-menu>
        </x-slot:sidebar>

        <x-slot:content>
            {{-- Bottom padding keeps content clear of the fixed tab bar on phones. --}}
            <div class="pb-32 lg:pb-0">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- BOTTOM TAB BAR (phones only; the sidebar covers lg+). Floating pill with a centre "+" to log. "More" opens the drawer. --}}
    @php
        $tabsLeft = [
            ['label' => 'Home', 'icon' => 'o-home', 'route' => 'dashboard', 'match' => ['dashboard']],
            ['label' => 'Diary', 'icon' => 'o-clock', 'route' => 'baby_actions.show', 'match' => ['baby_actions.*']],
        ];
        $tabsRight = [
            ['label' => 'Plan', 'icon' => 'o-calendar-days', 'route' => 'planning.show', 'match' => ['planning.*']],
        ];
        $tabClasses = 'flex flex-col items-center justify-center gap-0.5 min-h-14 w-full text-xs rounded-2xl';
    @endphp
    <nav aria-label="Main" class="lg:hidden fixed bottom-0 inset-x-0 z-[9] px-3" style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem)">
        <ul class="grid grid-cols-5 items-center bg-base-100 rounded-3xl shadow-lg border border-base-300 px-1">
            @foreach (array_merge($tabsLeft) as $tab)
                @php($active = request()->routeIs(...$tab['match']))
                <li>
                    <a href="{{ route($tab['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                       class="{{ $tabClasses }} {{ $active ? 'text-primary font-bold bg-primary/15' : 'text-base-content/70' }} active:bg-base-200">
                        <x-mary-icon :name="$tab['icon']" class="w-6 h-6" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li class="flex justify-center">
                <a href="{{ route('baby_actions.create') }}" wire:navigate aria-label="Log something"
                   class="-mt-8 flex items-center justify-center w-16 h-16 rounded-full bg-primary text-primary-content shadow-lg border-4 border-base-200 active:scale-95 transition-transform">
                    <x-mary-icon name="o-plus" class="w-8 h-8" />
                </a>
            </li>
            @foreach ($tabsRight as $tab)
                @php($active = request()->routeIs(...$tab['match']))
                <li>
                    <a href="{{ route($tab['route']) }}" wire:navigate @if ($active) aria-current="page" @endif
                       class="{{ $tabClasses }} {{ $active ? 'text-primary font-bold bg-primary/15' : 'text-base-content/70' }} active:bg-base-200">
                        <x-mary-icon :name="$tab['icon']" class="w-6 h-6" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li>
                <label for="main-drawer" role="button" aria-label="More" class="{{ $tabClasses }} {{ request()->routeIs('babies.*', 'notifications.*', 'medications.*') ? 'text-primary font-bold bg-primary/15' : 'text-base-content/70' }} active:bg-base-200 cursor-pointer">
                    <x-mary-icon name="o-ellipsis-horizontal" class="w-6 h-6" />
                    <span>More</span>
                </label>
            </li>
        </ul>
    </nav>

    <x-mary-toast />

    @livewireScripts
</body>
</html>
