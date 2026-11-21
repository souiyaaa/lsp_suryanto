<?php

namespace App\Http\Requests\Admin;

use App\Enums\RentalType;
use App\Traits\HasPaymentProofRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtensionRequest extends FormRequest
{
    use HasPaymentProofRule;

    public function authorize(): bool
    {
        return true; // the admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'number_of_days' => ['required_if:rental_type,'.RentalType::Harian->value, 'nullable', 'integer', 'min:1'],
            // The garage the admin confirmed in the popup.
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'payment_proof' => $this->paymentProofRules(),
        ];
    }
}
