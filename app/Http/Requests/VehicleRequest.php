<?php
namespace App\Http\Requests;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    private const MAX_PLATE_LENGTH = 15;

    public function authorize(): bool
    {
        return true;
    }

    /** Clears fields the chosen type doesn't use, so a sepeda never saves a stray plate. */
    protected function prepareForValidation(): void
    {
        $vehicleType = VehicleType::tryFrom((string) $this->input('type'));

        $this->merge([
            'plate_number' => $vehicleType?->requiresPlate() ? strtoupper(trim((string) $this->input('plate_number'))) : null,
            'max_battery_kwh' => $vehicleType?->requiresBattery() ? $this->input('max_battery_kwh') : null,
        ]);
    }

    public function rules(): array
    {
        $vehicleType = VehicleType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => [
                Rule::requiredIf($vehicleType?->requiresPlate() ?? false),
                'nullable', 'string', 'max:' . self::MAX_PLATE_LENGTH,
                Rule::unique('vehicles', 'plate_number'),
            ],
            'brand_model' => ['required', 'string', 'max:100'],
            'max_battery_kwh' => [
                Rule::requiredIf($vehicleType?->requiresBattery() ?? false),
                'nullable', 'numeric', 'gt:0', 'max:9999.99',
            ],
        ];
    }
}