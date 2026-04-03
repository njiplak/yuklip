<?php

namespace App\Exports\Sheets;

use App\Models\UpsellLog;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class UpsellSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string
    {
        return 'Upsell';
    }

    public function query()
    {
        return UpsellLog::query()
            ->with(['booking:id,guest_name,suite_name', 'offer:id,title'])
            ->where('sent_at', '>=', $this->from)
            ->where('sent_at', '<=', $this->to->endOfDay())
            ->orderBy('sent_at');
    }

    public function headings(): array
    {
        return ['Date', 'Guest', 'Suite', 'Offer', 'Outcome', 'Revenue (MAD)'];
    }

    public function map($row): array
    {
        return [
            $row->sent_at->format('Y-m-d H:i'),
            $row->booking?->guest_name ?? '—',
            $row->booking?->suite_name ?? '—',
            $row->offer?->title ?? '—',
            $row->outcome,
            $row->revenue_generated ?? 0,
        ];
    }
}
