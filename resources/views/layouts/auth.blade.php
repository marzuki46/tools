<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') - Juki Tools</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-gradient-to-br from-gray-900 via-indigo-950 to-gray-900 text-gray-900 antialiased min-h-screen flex items-center justify-center p-4">
    <main class="w-full">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>