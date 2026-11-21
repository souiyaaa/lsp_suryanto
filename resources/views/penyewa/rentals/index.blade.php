@inject('chargingService', 'App\Services\ChargingService')

<x-page title="Sewa Saya">
    <x-slot name="actions">
        <a href="{{ route('rentals.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm">Sewa Baru</a>
    </x-slot>

    <div class="space-y-6">
    @forelse ($rentals as $rental)
        @php
            $segments = collect([$rental])->merge($rental->extensions->sortBy('start_date'));
            $activeSegment = $chargingService->findActiveSegment($rental);
        @endphp
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-semibold">{{ $rental->garage->code }} · {{ $rental->vehicle->brand_model }} {{ $rental->vehicle->plate_number }}</div>
                    <div class="text-sm text-gray-500">{{ $rental->rental_type->label() }} · {{ \App\Support\DateFormatter::format($rental->start_date) }} – {{ \App\Support\DateFormatter::format($rental->end_date) }} ({{ $rental->numberOfDays() }} hari)</div>
                    <div class="text-sm mt-1">Sewa: {{ \App\Support\Rupiah::format($rental->numberOfDays() * $rental->garage->daily_price) }}</div>
                </div>
                @if ($activeSegment?->garage->type->hasCharging())
                    <a href="{{ route('charging.create', $rental) }}" class="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm">Bayar Pengecasan</a>
                @endif
            </div>

            @foreach ($segments as $segment)
                @if (! $loop->first)
                    <div class="mt-4 border-t pt-3 text-sm">
                        <div class="font-medium">Perpanjangan: {{ \App\Support\DateFormatter::format($segment->start_date) }} – {{ \App\Support\DateFormatter::format($segment->end_date) }} ({{ $segment->numberOfDays() }} hari)</div>
                        @if ($segment->garage_id !== $segments[$loop->index - 1]->garage_id)
                            <div class="mt-1 px-3 py-2 bg-yellow-100 text-yellow-800 rounded">Mulai {{ \App\Support\DateFormatter::format($segment->start_date) }} slot Anda {{ $segment->garage->code }}</div>
                        @endif
                    </div>
                @endif

                @foreach ($segment->chargingTransactions as $chargingTransaction)
                    <div class="mt-2 text-sm text-gray-700">
                        Pengecasan {{ $chargingTransaction->created_at->format('d M Y') }}:
                        {{ $chargingTransaction->current_kwh }} kWh → penuh
                        ({{ $chargingTransaction->kwh_needed }} kWh) ·
                        {{ \App\Support\Rupiah::format($chargingService->costOf($chargingTransaction)) }}
                    </div>
                @endforeach
            @endforeach
        </div>
    @empty
        <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-500">Belum ada sewa.</div>
    @endforelse
    </div>
</x-page>
