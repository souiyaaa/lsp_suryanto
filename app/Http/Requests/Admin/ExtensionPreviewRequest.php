<?php

namespace App\Http\Requests\Admin;

use App\Enums\RentalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Runs before the admin confirms the popup: no garage choice and no upload yet. */
class ExtensionPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'number_of_days' => ['required_if:rental_type,'.RentalType::Harian->value, 'nullable', 'integer', 'min:1'],
            'rental_id' => ['required', 'integer'],
        ];
    }
}
