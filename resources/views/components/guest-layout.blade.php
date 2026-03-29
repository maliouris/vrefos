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
<body class="font-sans antialiased bg-base-200 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-4">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold">{{ config('app.name', 'Vrefos') }}</h1>
        </div>
        <x-mary-card>
            {{ $slot }}
        </x-mary-card>
    </div>
    @livewireScripts
</body>
</html>
