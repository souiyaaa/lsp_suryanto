<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <x-application-logo class="w-24 h-24 fill-current text-indigo-600" />

            <h1 class="mt-6 text-3xl sm:text-4xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-3 max-w-md text-gray-600">
                Sewa garasi harian atau bulanan untuk sepeda, sepeda motor, mobil, dan mobil listrik
                lengkap dengan pengecasan.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <a href="{{ route('login') }}" class="px-6 py-3 bg-indigo-600 text-white rounded-md font-medium hover:bg-indigo-700">Masuk</a>
                <a href="{{ route('register') }}" class="px-6 py-3 bg-white text-gray-800 border border-gray-300 rounded-md font-medium hover:bg-gray-50">Daftar</a>
            </div>
        </div>
    </body>
</html>
