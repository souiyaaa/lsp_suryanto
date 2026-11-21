<?php
namespace App\Http\Requests;

use App\Traits\HasPaymentProofRule;
use Illuminate\Foundation\Http\FormRequest;

class ChargingRequest extends FormRequest
{
    use HasPaymentProofRule;

    public function authorize(): bool
    {
        return true; // ownership is checked by RentalPolicy in the controller
    }

    public function rules(): array
    {
        return [
            // The upper limit (below the vehicle's max) is checked in ChargingService.
            'current_kwh' => ['required', 'numeric', 'min:0'],
            'payment_proof' => $this->paymentProofRules(),
        ];
    }
}