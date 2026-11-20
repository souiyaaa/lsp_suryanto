@props(['code', 'title', 'message'])

<x-layouts.app :title="$code . ' ' . $title">
    <div class="flex flex-col items-center justify-center text-center py-24">
        <p class="text-7xl font-bold text-gray-300">{{ $code }}</p>
        <h1 class="mt-4 text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-2 text-gray-500 max-w-md">{{ $message }}</p>
        <a href="{{ url('/') }}" class="mt-8 px-4 py-2 rounded bg-gray-900 text-white hover:bg-gray-700">
            Back to home
        </a>
    </div>
</x-layouts.app>