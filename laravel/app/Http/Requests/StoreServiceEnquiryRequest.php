<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::in(['domestic', 'commercial', 'care_home'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'required_if:preferred_contact,phone', 'string', 'max:30'],
            'postcode' => ['required', 'string', 'max:12', 'regex:/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i'],
            'property_type' => ['required', Rule::in(['house', 'flat', 'office', 'retail', 'communal', 'care_home', 'other'])],
            'approximate_size' => ['nullable', 'string', 'max:100'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'organisation' => ['exclude_if:service_type,domestic', 'required_if:service_type,commercial,care_home', 'nullable', 'string', 'max:180'],
            'site_details' => ['exclude_if:service_type,domestic', 'required_if:service_type,commercial,care_home', 'nullable', 'string', 'max:500'],
            'frequency' => ['required', Rule::in(['one_off', 'weekly', 'fortnightly', 'monthly', 'discuss'])],
            'preferred_contact' => ['required', Rule::in(['email', 'phone'])],
            'preferred_start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'requirements' => ['required', 'string', 'min:10', 'max:3000'],
            'privacy_acknowledged' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
