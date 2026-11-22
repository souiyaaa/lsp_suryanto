<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The charging fields only. The preview form sends rental_id; the save route carries it in the URL. */
class ChargingPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is checked by RentalPolicy in the controller
    }

    public function rules(): array
    {
        return [
            // The upper limit (below the vehicle's max) is checked in ChargingService.
            'current_kwh' => ['required', 'numeric', 'min:0'],
            'rental_id' => ['required', 'integer'],
        ];
    }
}
