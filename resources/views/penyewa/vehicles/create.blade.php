<x-page title="Tambah Kendaraan">
    <form method="POST" action="{{ route('vehicles.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 max-w-xl"
          x-data="{ type: '{{ old('type') }}' }">
        @csrf
        <div>
            <x-input-label for="type" value="Tipe kendaraan" />
            <select id="type" name="type" x-model="type" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Pilih tipe</option>
                @foreach ($vehicleTypes as $vehicleType)
                    <option value="{{ $vehicleType->value }}">{{ $vehicleType->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>
        <div x-show="type && type !== 'sepeda'">
            <x-input-label for="plate_number" value="Nomor plat" />
            <x-text-input id="plate_number" name="plate_number" class="mt-1 block w-full" :value="old('plate_number')" />
            <x-input-error :messages="$errors->get('plate_number')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="brand_model" value="Merek & model" />
            <x-text-input id="brand_model" name="brand_model" class="mt-1 block w-full" :value="old('brand_model')" />
            <x-input-error :messages="$errors->get('brand_model')" class="mt-2" />
        </div>
        <div x-show="type === 'mobil_listrik'">
            <x-input-label for="max_battery_kwh" value="Kapasitas baterai maksimum (kWh)" />
            <x-text-input id="max_battery_kwh" name="max_battery_kwh" type="number" step="0.01" class="mt-1 block w-full" :value="old('max_battery_kwh')" />
            <x-input-error :messages="$errors->get('max_battery_kwh')" class="mt-2" />
        </div>
        <x-primary-button>Simpan</x-primary-button>
    </form>
</x-page>
