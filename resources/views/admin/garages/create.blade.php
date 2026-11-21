<x-page title="Buat Garasi">
    <form method="POST" action="{{ route('admin.garages.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 max-w-xl"
          x-data="{ type: '{{ old('type') }}' }">
        @csrf
        <div>
            <x-input-label value="Tipe garasi" />
            <select name="type" x-model="type" class="mt-1 block w-full border-gray-300 rounded-md">
                <option value="">Pilih tipe</option>
                @foreach ($garageTypes as $garageType)
                    <option value="{{ $garageType->value }}">{{ $garageType->label() }} ({{ $garageType->codePrefix() }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Jumlah (maks 999 per tipe)" />
            <x-text-input name="amount" type="number" min="1" class="mt-1 block w-full" :value="old('amount')" />
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Harga sewa harian (Rp)" />
            <x-text-input name="daily_price" type="number" min="1" class="mt-1 block w-full" :value="old('daily_price')" />
            <x-input-error :messages="$errors->get('daily_price')" class="mt-2" />
        </div>
        <div x-show="type === 'mobil_listrik'">
            <x-input-label value="Harga pengecasan per kWh (Rp)" />
            <x-text-input name="charging_price_per_kwh" type="number" min="1" class="mt-1 block w-full" :value="old('charging_price_per_kwh')" />
            <x-input-error :messages="$errors->get('charging_price_per_kwh')" class="mt-2" />
        </div>
        <x-primary-button>Buat</x-primary-button>
    </form>
</x-page>
