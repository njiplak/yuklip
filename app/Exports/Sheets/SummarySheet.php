<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SummarySheet implements FromArray, WithTitle
{
    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
        protected array $metrics,
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function array(): array
    {
        $m = $this->metrics;

        $rows = [
            ['Monthly Report — Riad Larbi Khalis'],
            ['Period', $this->from->format('F Y')],
            [],
            ['BOOKINGS'],
            ['Total Bookings', $m['total_bookings']],
            ['Cancellations', $m['cancellations']],
            [],
            ['REVENUE'],
            ['Accommodation', $m['accommodation_revenue'], 'MAD'],
            ['Upsell', $m['upsell_revenue'], 'MAD'],
            ['Total Revenue', $m['total_revenue'], 'MAD'],
            [],
            ['EXPENSES'],
            ['Total Expenses', $m['total_expenses'], 'MAD'],
            [],
            ['NET REVENUE', $m['net_revenue'], 'MAD'],
            [],
            ['PERFORMANCE'],
            ['Occupancy Rate', $m['occupancy_rate'] . '%'],
            ['Upsell Sent', $m['upsells_sent']],
            ['Upsell Accepted', $m['upsells_accepted']],
            ['Upsell Conversion', $m['upsell_conversion'] . '%'],
        ];

        if (!empty($m['revenue_by_suite'])) {
            $rows[] = [];
            $rows[] = ['REVENUE BY SUITE'];
            $rows[] = ['Suite', 'Revenue (MAD)', 'Bookings'];
            foreach ($m['revenue_by_suite'] as $suite) {
                $rows[] = [$suite['label'], $suite['total'], $suite['count']];
            }
        }

        return $rows;
    }
}
