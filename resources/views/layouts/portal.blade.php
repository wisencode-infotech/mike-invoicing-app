<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans antialiased">
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
            {{ $slot }}

            <p class="mt-8 text-center text-xs text-gray-400">
                {{ __('Secured by :app', ['app' => config('app.name')]) }}
            </p>
        </div>
    </body>
</html>
