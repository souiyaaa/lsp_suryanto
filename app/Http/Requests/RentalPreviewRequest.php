<?php

namespace App\Http\Requests;

use App\Enums\RentalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The rental fields only. The preview runs before the user attaches a payment proof. */
class RentalPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)],
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'number_of_days' => ['required_if:rental_type,'.RentalType::Harian->value, 'nullable', 'integer', 'min:1'],
            // Required for E garages; RentalService checks that, since it knows the garage type.
            'current_kwh' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
