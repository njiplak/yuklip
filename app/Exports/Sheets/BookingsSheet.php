<?php

namespace App\Exports\Sheets;

use App\Models\Booking;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class BookingsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string
    {
        return 'Bookings';
    }

    public function query()
    {
        return Booking::query()
            ->where('created_at', '>=', $this->from)
            ->where('created_at', '<=', $this->to->endOfDay())
            ->orderBy('check_in');
    }

    public function headings(): array
    {
        return ['Guest', 'Suite', 'Check-in', 'Check-out', 'Nights', 'Source', 'Status', 'Amount (MAD)'];
    }

    public function map($row): array
    {
        return [
            $row->guest_name,
            $row->suite_name,
            $row->check_in->format('Y-m-d'),
            $row->check_out->format('Y-m-d'),
            $row->num_nights,
            $row->booking_source,
            $row->booking_status,
            $row->total_amount,
        ];
    }
}
