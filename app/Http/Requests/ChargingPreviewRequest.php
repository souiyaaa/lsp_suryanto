<?php
namespace App\Http\Requests;

use Illuminate\Support\Arr;

class ChargingPreviewRequest extends ChargingRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['payment_proof']) + ['rental_id' => ['required', 'integer']];
    }
}