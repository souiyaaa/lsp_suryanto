<?php
namespace App\Http\Requests;

use Illuminate\Support\Arr;

class RentalPreviewRequest extends RentalRequest
{
    /** Same rules as saving, minus the upload: the preview runs before the user attaches it. */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['payment_proof']);
    }
}