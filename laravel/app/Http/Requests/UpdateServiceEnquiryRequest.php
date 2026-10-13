<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->services_staff;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(config('services_site.statuses')))],
            'staff_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
