<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->services_staff;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'eyebrow' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:6000'],
            'tasks_text' => ['required', 'string', 'max:3000'],
            'extras_text' => ['nullable', 'string', 'max:3000'],
            'preparation' => ['required', 'string', 'max:3000'],
            'frequency' => ['required', 'string', 'max:300'],
            'faqs' => ['array', 'max:10'],
            'faqs.*.question' => ['nullable', 'required_with:faqs.*.answer', 'string', 'max:200'],
            'faqs.*.answer' => ['nullable', 'required_with:faqs.*.question', 'string', 'max:1500'],
            'published' => ['required', 'boolean'],
        ];
    }
}
