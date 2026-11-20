@php
    // Add one entry per CRUD module once the case study is known.
    $menuItems = [
        ['label' => 'Dashboard', 'route' => 'home'],
    ];
@endphp

<nav class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

        <ul class="flex gap-6 text-sm">
            @foreach ($menuItems as $menuItem)
                <li>
                    <a href="{{ route($menuItem['route']) }}"
                       @class([
                           'hover:text-gray-900',
                           'text-gray-900 font-medium' => request()->routeIs($menuItem['route'] . '*'),
                           'text-gray-500' => ! request()->routeIs($menuItem['route'] . '*'),
                       ])>
                        {{ $menuItem['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>