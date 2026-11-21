<x-page title="Dashboard Admin">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-gray-500">Total pendapatan</div>
            <div class="text-2xl font-semibold">{{ \App\Support\Rupiah::format($totalRevenue) }}</div>
        </div>
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-gray-500">Total penyewa</div>
            <div class="text-2xl font-semibold">{{ $totalPenyewa }}</div>
        </div>
    </div>

    <h3 class="font-semibold mb-3">Status slot hari ini</h3>
    <div class="space-y-4">
    @forelse ($garagesByType as $typeLabel => $garages)
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="font-medium mb-2">{{ $typeLabel }} · {{ $garages->where('is_used', true)->count() }} terpakai / {{ $garages->count() }}</div>
            <div class="flex flex-wrap gap-2">
                @foreach ($garages as $garage)
                    <span @class(['px-2 py-1 rounded text-xs', 'bg-red-100 text-red-800' => $garage->is_used, 'bg-green-100 text-green-800' => ! $garage->is_used])>
                        {{ $garage->code }} {{ $garage->is_used ? 'terpakai' : 'kosong' }}
                    </span>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white shadow-sm sm:rounded-lg p-4 text-gray-500">Belum ada garasi.</div>
    @endforelse
    </div>
</x-page>
