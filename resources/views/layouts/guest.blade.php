<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vrefos') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-base-200 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-4">
        <div class="text-center mb-6">
            <picture>
                <source srcset="{{ asset('vrefos-logo-dark.svg') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('vrefos-logo.svg') }}" alt="{{ config('app.name', 'Vrefos') }}" class="h-12 w-auto mx-auto">
            </picture>
        </div>
        <x-mary-card>
            {{ $slot }}
        </x-mary-card>
    </div>
    @livewireScripts
</body>
</html>
