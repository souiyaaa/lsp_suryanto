<x-page title="Sewa Baru">
    <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data"
          class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 max-w-xl" x-data="rentalForm" x-init="init()">
        @csrf
        <div>
            <x-input-label value="Kendaraan" />
            <select name="vehicle_id" x-model="vehicleId" @change="loadTypes()" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Pilih kendaraan</option>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">{{ $vehicle->type->label() }} – {{ $vehicle->brand_model }} {{ $vehicle->plate_number }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Jenis sewa" />
            <select name="rental_type" x-model="rentalType" @change="loadGarages()" class="mt-1 block w-full border-gray-300 rounded-md">
                @foreach ($rentalTypes as $rentalType)
                    <option value="{{ $rentalType->value }}">{{ $rentalType->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label value="Tanggal mulai" />
            <input type="date" name="start_date" x-model="startDate" :min="today" @change="loadGarages()" class="mt-1 block w-full border-gray-300 rounded-md">
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>
        <div x-show="rentalType === 'harian'">
            <x-input-label value="Jumlah hari" />
            <input type="number" min="1" name="number_of_days" x-model="days" @input.debounce.400ms="loadGarages()" class="mt-1 block w-full border-gray-300 rounded-md">
            <x-input-error :messages="$errors->get('number_of_days')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Tipe garasi" />
            <select x-model="garageType" @change="loadGarages()" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Pilih tipe garasi</option>
                <template x-for="type in garageTypes" :key="type.value"><option :value="type.value" x-text="type.label"></option></template>
            </select>
        </div>
        <div>
            <x-input-label value="Garasi tersedia" />
            <select name="garage_id" x-model="garageId" @change="loadPreview()" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Pilih garasi</option>
                <template x-for="garage in garages" :key="garage.id"><option :value="garage.id" x-text="garage.code + ' – ' + garage.daily_price_label + '/hari'"></option></template>
            </select>
            <x-input-error :messages="$errors->get('garage_id')" class="mt-2" />
        </div>
        <div x-show="garageType === 'mobil_listrik'">
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
        <div x-show="preview" class="p-3 bg-gray-50 rounded text-sm space-y-1">
            <div>Selesai: <span x-text="preview?.end_date"></span> (<span x-text="preview?.number_of_days"></span> hari)</div>
            <div>Sewa: <span x-text="preview?.rent"></span></div>
            <div x-show="preview?.charging">Pengecasan: <span x-text="preview?.charging?.kwh_needed"></span> kWh</div>
            <div class="font-semibold">Total: <span x-text="preview?.total"></span></div>
        </div>
        <x-primary-button>Simpan</x-primary-button>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('rentalForm', () => ({
                today: '{{ today()->toDateString() }}',
                vehicleId: '{{ old('vehicle_id') }}', rentalType: '{{ old('rental_type', 'harian') }}',
                startDate: '{{ old('start_date', today()->toDateString()) }}', days: '{{ old('number_of_days', 1) }}',
                garageType: '', garageId: '', currentKwh: '{{ old('current_kwh') }}',
                garageTypes: [], garages: [], preview: null, error: '',
                init() { if (this.vehicleId) this.loadTypes(); },
                async api(url, params) {
                    const response = await fetch(url + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } });
                    const json = await response.json();
                    if (!json.success) { throw new Error(json.errors ? Object.values(json.errors).flat()[0] : json.message); }
                    return json.data;
                },
                async loadTypes() {
                    this.garageType = ''; this.garages = []; this.garageId = ''; this.preview = null; this.error = '';
                    if (!this.vehicleId) { this.garageTypes = []; return; }
                    try { this.garageTypes = await this.api('{{ route('api.garage-types') }}', { vehicle_id: this.vehicleId }); }
                    catch (e) { this.error = e.message; }
                },
                async loadGarages() {
                    this.garages = []; this.garageId = ''; this.preview = null; this.error = '';
                    if (!this.garageType || !this.startDate) return;
                    const params = { garage_type: this.garageType, rental_type: this.rentalType, start_date: this.startDate };
                    if (this.rentalType === 'harian') params.number_of_days = this.days;
                    try { this.garages = await this.api('{{ route('api.available-garages') }}', params); }
                    catch (e) { this.error = e.message; }
                },
                async loadPreview() {
                    this.preview = null; this.error = '';
                    if (!this.garageId || (this.garageType === 'mobil_listrik' && this.currentKwh === '')) return;
                    const params = { vehicle_id: this.vehicleId, garage_id: this.garageId, rental_type: this.rentalType, start_date: this.startDate };
                    if (this.rentalType === 'harian') params.number_of_days = this.days;
                    if (this.garageType === 'mobil_listrik') params.current_kwh = this.currentKwh;
                    try { this.preview = await this.api('{{ route('api.rental-preview') }}', params); }
                    catch (e) { this.error = e.message; }
                },
            }));
        });
    </script>
</x-page>
