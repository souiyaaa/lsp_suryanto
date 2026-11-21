<x-page title="Bayar Pengecasan">
    <form method="POST" action="{{ route('charging.store', $rental) }}" enctype="multipart/form-data"
          class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 max-w-xl" x-data="chargingForm" >
        @csrf
        <div class="text-sm text-gray-600">
            Garasi {{ $activeSegment->garage->code }} · {{ $rental->vehicle->brand_model }} ·
            kapasitas {{ $rental->vehicle->max_battery_kwh }} kWh ·
            {{ \App\Support\Rupiah::format($activeSegment->garage->charging_price_per_kwh) }}/kWh
        </div>
        <div>
            <x-input-label value="Daya baterai saat ini (kWh)" />
            <input type="number" step="0.01" min="0" name="current_kwh" x-model="currentKwh" @input.debounce.400ms="loadPreview()" class="mt-1 block w-full border-gray-300 rounded-md">
            <x-input-error :messages="$errors->get('current_kwh')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Bukti pembayaran (jpg, png, webp, maks 2 MB)" />
            <input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full">
            <x-input-error :messages="$errors->get('payment_proof')" class="mt-2" />
        </div>
        <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
        <div x-show="preview" class="p-3 bg-gray-50 rounded text-sm">
            Perlu <span x-text="preview?.kwh_needed"></span> kWh · Biaya <span class="font-semibold" x-text="preview?.cost"></span>
        </div>
        <x-primary-button>Simpan</x-primary-button>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('chargingForm', () => ({
                currentKwh: '{{ old('current_kwh') }}', preview: null, error: '',
                async loadPreview() {
                    this.preview = null; this.error = '';
                    if (this.currentKwh === '') return;
                    const query = new URLSearchParams({ rental_id: '{{ $rental->id }}', current_kwh: this.currentKwh });
                    const response = await fetch('{{ route('api.charging-preview') }}?' + query, { headers: { Accept: 'application/json' } });
                    const json = await response.json();
                    if (json.success) { this.preview = json.data; }
                    else { this.error = json.errors ? Object.values(json.errors).flat()[0] : json.message; }
                },
            }));
        });
    </script>
</x-page>
