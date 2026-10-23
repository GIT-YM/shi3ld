<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VisualContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->services_staff;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', 'in:render,save,publish,preview,revision,restore,media,upload,published,assets,create_asset,load_asset,unpublish_asset,export_design,import_design,submit_review,schedule,cancel_schedule,review_queue'],
            'version' => ['required', 'integer', 'min:0'],
            'live_hash' => ['required', 'string', 'size:64'],
            'payload' => ['required_if:intent,render,save', 'nullable', 'string', 'max:600000'],
            'revision_id' => ['required_if:intent,revision,restore', 'nullable', 'integer', 'min:1'],
            'asset_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:160'],
            'asset_payload' => ['nullable', 'string', 'max:650000'],
            'scheduled_at' => ['nullable', 'string', 'max:30'],
            'package' => ['required_if:intent,import_design', 'nullable', 'file', 'max:30000'],
            'image' => ['required_if:intent,upload', 'nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'],
        ];
    }
}
