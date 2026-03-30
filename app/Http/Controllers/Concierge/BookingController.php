<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\BookingContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\BookingRequest;
use App\Utils\WebResponse;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    protected BookingContract $service;

    public function __construct(BookingContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/booking/index');
    }

    public function fetch()
    {
        $data = $this->service->all(
            allowedFilters: [],
            allowedSorts: [],
            withPaginate: true,
            perPage: request()->get('per_page', 10),
        );
        return response()->json($data);
    }

    public function create()
    {
        return Inertia::render('concierge/booking/form');
    }

    public function store(BookingRequest $request)
    {
        $data = $this->service->create($request->validated());
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function show($id)
    {
        $data = $this->service->find($id, ['currentOffer', 'upsellLogs.offer', 'whatsappMessages']);
        return Inertia::render('concierge/booking/form', [
            'booking' => $data,
        ]);
    }

    public function update(BookingRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function destroy($id)
    {
        $data = $this->service->destroy($id);
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }

    public function destroy_bulk(Request $request)
    {
        $data = $this->service->bulkDeleteByIds($request->ids ?? []);
        return WebResponse::response($data, 'backoffice.concierge.booking.index');
    }
}
