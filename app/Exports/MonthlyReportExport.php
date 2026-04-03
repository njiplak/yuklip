<?php

namespace App\Exports;

use App\Models\Booking;
use App\Models\Transaction;
use App\Models\UpsellLog;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonthlyReportExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
        protected array $metrics,
    ) {}

    public function sheets(): array
    {
        return [
            'Summary' => new Sheets\SummarySheet($this->from, $this->to, $this->metrics),
            'Transactions' => new Sheets\TransactionsSheet($this->from, $this->to),
            'Bookings' => new Sheets\BookingsSheet($this->from, $this->to),
            'Upsell' => new Sheets\UpsellSheet($this->from, $this->to),
        ];
    }
}
