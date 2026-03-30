<?php

namespace App\Http\Requests\Concierge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', Rule::in(['room_revenue', 'upsell', 'supplies', 'staff', 'maintenance'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'transaction_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'card', 'bank_transfer'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'recorded_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
