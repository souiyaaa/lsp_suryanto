<x-page title="Kendaraan Saya">
    <x-slot name="actions">
        <a href="{{ route('vehicles.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm">Tambah Kendaraan</a>
    </x-slot>

    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left"><tr>
                <th class="p-3">Tipe</th><th class="p-3">Plat</th><th class="p-3">Merek / Model</th><th class="p-3">Baterai (kWh)</th>
            </tr></thead>
            <tbody>
            @forelse ($vehicles as $vehicle)
                <tr class="border-t">
                    <td class="p-3">{{ $vehicle->type->label() }}</td>
                    <td class="p-3">{{ $vehicle->plate_number ?? '-' }}</td>
                    <td class="p-3">{{ $vehicle->brand_model }}</td>
                    <td class="p-3">{{ $vehicle->max_battery_kwh ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="p-4 text-gray-500">Belum ada kendaraan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-page>
