<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Arr;

class ExtensionPreviewRequest extends ExtensionRequest
{
    /** Runs before the admin confirms the popup: no garage choice and no upload yet. */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['payment_proof', 'garage_id']) + ['rental_id' => ['required', 'integer']];
    }
}
