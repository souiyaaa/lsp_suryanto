<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin middleware already guards the route
    }

    public function rules(): array
    {
        // The admin must tick "KTP sudah dicek" before approving (FR-07).
        return ['is_ktp_checked' => ['accepted']];
    }
}
