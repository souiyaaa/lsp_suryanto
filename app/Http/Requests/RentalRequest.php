<?php
namespace App\Http\Requests;

use App\Enums\RentalType;
use App\Traits\HasPaymentProofRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RentalRequest extends FormRequest
{
    use HasPaymentProofRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only the penyewa's own vehicles (NFR-02).
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)],
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            // The upper limit (days in the month − 1) is checked in RentalService.
            'number_of_days' => ['required_if:rental_type,' . RentalType::Harian->value, 'nullable', 'integer', 'min:1'],
            // Required for E garages; RentalService checks that, since it knows the garage type.
            'current_kwh' => ['nullable', 'numeric', 'min:0'],
            'payment_proof' => $this->paymentProofRules(),
        ];
    }
}