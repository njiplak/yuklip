<?php

namespace App\Http\Requests\Concierge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offer_code' => ['required', 'string', 'max:100', Rule::unique('offers')->ignore($this->route('id'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(['wellness', 'dining', 'experience', 'transport'])],
            'timing_rule' => ['required', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['required', 'boolean'],
            'max_sends_per_stay' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
