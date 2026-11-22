<?php

namespace App\Http\Requests\Admin;

use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GarageBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin middleware already guards the route
    }

    protected function prepareForValidation(): void
    {
        $garageType = GarageType::tryFrom((string) $this->input('type'));

        if (! $garageType?->hasCharging()) {
            $this->merge(['charging_price_per_kwh' => null]);
        }
    }

    public function rules(): array
    {
        $garageType = GarageType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(GarageType::class)],
            // The remaining capacity (999 − existing) is checked in GarageBatchService.
            'amount' => ['required', 'integer', 'min:1', 'max:'.Garage::MAX_GARAGES_PER_TYPE],
            'daily_price' => ['required', 'integer', 'min:1'],
            'charging_price_per_kwh' => [
                Rule::requiredIf($garageType?->hasCharging() ?? false),
                'nullable', 'integer', 'min:1',
            ],
        ];
    }
}
