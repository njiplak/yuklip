<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionsSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected Carbon $from,
        protected Carbon $to,
    ) {}

    public function title(): string
    {
        return 'Transactions';
    }

    public function query()
    {
        return Transaction::query()
            ->where('transaction_date', '>=', $this->from->toDateString())
            ->where('transaction_date', '<=', $this->to->toDateString())
            ->orderBy('transaction_date');
    }

    public function headings(): array
    {
        return ['Date', 'Type', 'Category', 'Description', 'Amount (MAD)', 'Recorded By'];
    }

    public function map($row): array
    {
        return [
            $row->transaction_date->format('Y-m-d'),
            $row->type,
            $row->category,
            $row->description,
            $row->amount,
            $row->recorded_by,
        ];
    }
}
