<?php

namespace App\Http\Requests\Admin;

use App\Traits\HasPaymentProofRule;

/** A saved extension is an extension preview plus the confirmed garage and the payment proof. */
class ExtensionRequest extends ExtensionPreviewRequest
{
    use HasPaymentProofRule;

    /** The save route binds the rental from the URL, so it fills the field the preview takes from the form. */
    protected function prepareForValidation(): void
    {
        $this->merge(['rental_id' => $this->route('rental')->getKey()]);
    }

    public function rules(): array
    {
        return parent::rules() + [
            // The garage the admin confirmed in the popup.
            'garage_id' => ['required', 'integer', 'exists:garages,id'],
            'payment_proof' => $this->paymentProofRules(),
        ];
    }
}
