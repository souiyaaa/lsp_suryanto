@props(['title' => config('app.name')])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-800">
    <x-navbar />

    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-8">
        @isset($header)
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-semibold">{{ $header }}</h1>
                {{ $actions ?? '' }}
            </div>
        @endisset

        <x-alert />

        {{ $slot }}
    </main>

    <x-footer />
    <x-confirm-modal />

    <script src="{{ asset('js/api.js') }}"></script>
    @stack('scripts')
</body>
</html>