<?php

namespace App\Traits;

use App\Services\PaymentProofUploader;

/**
 * One definition of the payment-proof upload rule, shared by every
 * FormRequest that takes an image. Rejected here, before any Cloudinary call.
 */
trait HasPaymentProofRule
{
    /** @return array<int, string> */
    protected function paymentProofRules(): array
    {
        return [
            'required',
            'file',
            'image',
            'mimes:'.implode(',', PaymentProofUploader::ALLOWED_MIMES),
            'max:'.PaymentProofUploader::MAX_PROOF_SIZE_KB,
        ];
    }
}
