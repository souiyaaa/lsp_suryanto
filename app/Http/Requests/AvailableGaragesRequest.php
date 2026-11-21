<?php
namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Enums\RentalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailableGaragesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'garage_type' => ['required', Rule::enum(GarageType::class)],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'number_of_days' => ['required_if:rental_type,' . RentalType::Harian->value, 'nullable', 'integer', 'min:1'],
        ];
    }
}