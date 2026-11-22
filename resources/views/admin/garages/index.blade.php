<x-page title="Daftar Garasi">
    <x-slot name="actions">
        <a href="{{ route('admin.garages.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm">Buat Garasi</a>
    </x-slot>

    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left"><tr>
                <th class="p-3">Kode</th><th class="p-3">Tipe</th><th class="p-3">Harga harian</th><th class="p-3">Harga per kWh</th><th class="p-3">Hari ini</th>
            </tr></thead>
            <tbody>
            @forelse ($garages as $garage)
                <tr class="border-t">
                    <td class="p-3">{{ $garage->code }}</td>
                    <td class="p-3">{{ $garage->type->label() }}</td>
                    <td class="p-3">{{ \App\Support\Rupiah::format($garage->daily_price) }}</td>
                    <td class="p-3">{{ $garage->charging_price_per_kwh ? \App\Support\Rupiah::format($garage->charging_price_per_kwh) : '-' }}</td>
                    <td class="p-3">{{ $garage->is_used ? 'Terpakai' : 'Kosong' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-gray-500">Belum ada garasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $garages->links() }}</div>
</x-page>
