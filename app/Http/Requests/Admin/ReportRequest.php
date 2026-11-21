<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::enum(ReportView::class)],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
