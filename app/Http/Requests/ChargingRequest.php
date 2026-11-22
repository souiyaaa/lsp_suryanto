<?php

namespace App\Http\Requests;

use App\Traits\HasPaymentProofRule;

/** A saved charging session is a charging preview plus the payment proof. */
class ChargingRequest extends ChargingPreviewRequest
{
    use HasPaymentProofRule;

    /** The save route has the rental in the URL, so it fills the field the preview takes from the form. */
    protected function prepareForValidation(): void
    {
        $this->merge(['rental_id' => $this->route('rental')]);
    }

    public function rules(): array
    {
        return parent::rules() + ['payment_proof' => $this->paymentProofRules()];
    }
}
