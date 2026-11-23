<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWeighingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'second_weight_kg' => ['required', 'numeric', 'gt:0', 'regex:/^(?:0|[1-9]\d{0,14})(?:\.\d{1,3})?$/'],
            'ticket_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
