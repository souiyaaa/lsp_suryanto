<?php

namespace App\Http\Requests;

use App\Traits\HasPaymentProofRule;

/** A saved rental is a rental preview plus the payment proof. */
class RentalRequest extends RentalPreviewRequest
{
    use HasPaymentProofRule;

    public function rules(): array
    {
        return parent::rules() + ['payment_proof' => $this->paymentProofRules()];
    }
}
