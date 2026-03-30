<?php

namespace App\Http\Requests\Concierge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lodgify_booking_id' => ['required', 'string', 'max:255', Rule::unique('bookings')->ignore($this->route('id'))],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:20'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'guest_nationality' => ['nullable', 'string', 'max:100'],
            'num_guests' => ['required', 'integer', 'min:1', 'max:20'],
            'suite_name' => ['required', 'string', Rule::in(['Suite Al Andalus', 'Suite Zitoun', 'Suite Atlas', 'Suite Menara'])],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'num_nights' => ['required', 'integer', 'min:1'],
            'booking_source' => ['required', 'string', 'max:100'],
            'booking_status' => ['required', 'string', Rule::in(['confirmed', 'checked_in', 'checked_out', 'cancelled'])],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'special_requests' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
