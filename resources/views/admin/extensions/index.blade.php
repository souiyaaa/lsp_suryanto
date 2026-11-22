<x-page title="Perpanjangan Sewa">
    <p class="text-sm text-gray-500 mb-3">Sewa yang aktif hari ini.</p>
    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left"><tr>
                <th class="p-3">Penyewa</th><th class="p-3">Kendaraan</th><th class="p-3">Garasi awal</th><th class="p-3">Berakhir</th><th class="p-3"></th>
            </tr></thead>
            <tbody>
            @forelse ($rentals as $rental)
                @php($lastEnd = $rental->extensions->max('end_date') ?? $rental->end_date)
                <tr class="border-t">
                    <td class="p-3">{{ $rental->penyewa->name }}</td>
                    <td class="p-3">{{ $rental->vehicle->brand_model }} {{ $rental->vehicle->plate_number }}</td>
                    <td class="p-3">{{ $rental->garage->code }}</td>
                    <td class="p-3">{{ \App\Support\DateFormatter::format($lastEnd) }}</td>
                    <td class="p-3"><a href="{{ route('admin.extensions.create', $rental) }}" class="text-indigo-600 underline">Perpanjang</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-gray-500">Tidak ada sewa aktif hari ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rentals->links() }}</div>
</x-page>
