<x-page title="Laporan Hasil Persewaan">
    <form method="GET" class="bg-white shadow-sm sm:rounded-lg p-4 mb-4 flex flex-wrap items-end gap-3" x-data="{ view: '{{ $reportView->value }}' }">
        <div>
            <x-input-label value="Laporan" />
            <select name="view" x-model="view" class="mt-1 border-gray-300 rounded-md">
                <option value="per_month">Per bulan</option>
                <option value="per_day">Per hari</option>
                <option value="per_garage_type">Per tipe garasi</option>
                <option value="per_penyewa">Per penyewa</option>
            </select>
        </div>
        <div x-show="view === 'per_month' || view === 'per_day'">
            <x-input-label value="Tahun" />
            <input type="number" name="year" value="{{ $year }}" class="mt-1 w-28 border-gray-300 rounded-md">
        </div>
        <div x-show="view === 'per_day'">
            <x-input-label value="Bulan" />
            <input type="number" name="month" min="1" max="12" value="{{ $month }}" class="mt-1 w-20 border-gray-300 rounded-md">
        </div>
        <x-primary-button>Tampilkan</x-primary-button>
        @if ($errors->any())<p class="text-sm text-red-600">{{ $errors->first() }}</p>@endif
    </form>

    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left"><tr>
                <th class="p-3">{{ match ($reportView) {
                    \App\Enums\ReportView::Monthly => 'Bulan',
                    \App\Enums\ReportView::Daily => 'Tanggal',
                    \App\Enums\ReportView::GarageType => 'Tipe garasi',
                    \App\Enums\ReportView::Penyewa => 'Penyewa',
                } }}</th>
                <th class="p-3">Jumlah sewa</th><th class="p-3">Pendapatan</th>
            </tr></thead>
            <tbody>
            @forelse ($rows->sortKeys() as $key => $row)
                <tr class="border-t">
                    <td class="p-3">{{ match ($reportView) {
                        \App\Enums\ReportView::Monthly => \Carbon\Carbon::create($year, $key, 1)->translatedFormat('F'),
                        \App\Enums\ReportView::Daily => $key.' '.\Carbon\Carbon::create($year, $month, 1)->translatedFormat('F Y'),
                        \App\Enums\ReportView::GarageType => \App\Enums\GarageType::from($key)->label(),
                        \App\Enums\ReportView::Penyewa => $key,
                    } }}</td>
                    <td class="p-3">{{ $row['rental_count'] }}</td>
                    <td class="p-3">{{ \App\Support\Rupiah::format($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-4 text-gray-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-page>
