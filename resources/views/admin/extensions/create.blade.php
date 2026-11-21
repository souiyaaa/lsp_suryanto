<x-page title="Perpanjang Sewa">
    <form method="POST" action="{{ route('admin.extensions.store', $rental) }}" enctype="multipart/form-data"
          class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 max-w-xl" x-data="extensionForm" @submit="if (!confirmed) $event.preventDefault()">
        @csrf
        <div class="text-sm text-gray-600">
            {{ $rental->vehicle->brand_model }} · segmen saat ini {{ $currentSegment->garage->code }} sampai
            {{ \App\Support\DateFormatter::format($currentSegment->end_date) }}
        </div>
        <div>
            <x-input-label value="Jenis perpanjangan" />
            <select name="rental_type" x-model="rentalType" class="mt-1 block w-full border-gray-300 rounded-md">
                @foreach ($rentalTypes as $rentalType)
                    <option value="{{ $rentalType->value }}">{{ $rentalType->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('rental_type')" class="mt-2" />
        </div>
        <div x-show="rentalType === 'harian'">
            <x-input-label value="Jumlah hari" />
            <input type="number" min="1" name="number_of_days" x-model="days" class="mt-1 block w-full border-gray-300 rounded-md">
            <x-input-error :messages="$errors->get('number_of_days')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Bukti pembayaran (jpg, png, webp, maks 2 MB)" />
            <input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full">
            <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />
        </div>
        <input type="hidden" name="garage_id" :value="preview?.garage_id">
        <x-input-error :messages="$errors->get('garage_id')" class="mt-2" />

        <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
        <button type="button" @click="loadPreview()" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm">Lanjut</button>

        <div x-show="preview" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full space-y-3">
                <h3 class="font-semibold">Konfirmasi perpanjangan</h3>
                <p class="text-sm">Periode: <span x-text="preview?.start_date"></span> – <span x-text="preview?.end_date"></span></p>
                <p x-show="preview?.is_slot_changed" class="px-3 py-2 bg-yellow-100 text-yellow-800 rounded text-sm">
                    Slot akan berubah ke <strong x-text="preview?.garage_code"></strong> dengan harga <strong x-text="preview?.daily_price"></strong>/hari.
                </p>
                <p x-show="!preview?.is_slot_changed" class="text-sm">Garasi <span x-text="preview?.garage_code"></span> · <span x-text="preview?.daily_price"></span>/hari</p>
                <p class="font-semibold">Biaya: <span x-text="preview?.fee"></span></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="preview = null" class="px-3 py-2 border rounded">Batal</button>
                    <button type="submit" @click="confirmed = true" class="px-3 py-2 bg-indigo-600 text-white rounded">Konfirmasi &amp; Simpan</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('extensionForm', () => ({
                rentalType: '{{ old('rental_type', 'harian') }}', days: '{{ old('number_of_days', 1) }}',
                preview: null, error: '', confirmed: false,
                async loadPreview() {
                    this.preview = null; this.error = ''; this.confirmed = false;
                    const params = { rental_id: '{{ $rental->id }}', rental_type: this.rentalType };
                    if (this.rentalType === 'harian') params.number_of_days = this.days;
                    const response = await fetch('{{ route('api.admin.extension-preview') }}?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
                    const json = await response.json();
                    if (json.success) { this.preview = json.data; }
                    else { this.error = json.errors ? Object.values(json.errors).flat()[0] : json.message; }
                },
            }));
        });
    </script>
</x-page>
