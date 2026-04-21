<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Contract\Concierge\BookingContract;
use App\Http\Controllers\Controller;
use App\Utils\WebResponse;
use Spatie\QueryBuilder\AllowedFilter;

class BookingController extends Controller
{
    protected BookingContract $service;

    public function __construct(BookingContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $result = $this->service->all(
            allowedFilters: [
                AllowedFilter::exact('booking_status'),
                AllowedFilter::exact('suite_name'),
                AllowedFilter::partial('guest_name'),
                AllowedFilter::callback('check_in_from', function ($q, $v) {
                    $q->whereDate('check_in', '>=', $v);
                }),
                AllowedFilter::callback('check_in_to', function ($q, $v) {
                    $q->whereDate('check_in', '<=', $v);
                }),
            ],
            allowedSorts: ['check_in', 'check_out', 'created_at'],
            withPaginate: true,
            relation: ['customer', 'currentOffer'],
            perPage: (int) request()->get('per_page', 10),
            orderColumn: 'check_in',
            orderPosition: 'desc',
        );

        return WebResponse::json($result, 'Bookings retrieved.');
    }

    public function show($id)
    {
        $result = $this->service->find($id, [
            'customer',
            'currentOffer',
            'upsellLogs.offer',
            'whatsappMessages',
        ]);

        return WebResponse::json($result, 'Booking retrieved.');
    }
}
