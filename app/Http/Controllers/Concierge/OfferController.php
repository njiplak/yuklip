<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\OfferContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\OfferRequest;
use App\Utils\WebResponse;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;

class OfferController extends Controller
{
    protected OfferContract $service;

    public function __construct(OfferContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/offer/index');
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
        return Inertia::render('concierge/offer/form');
    }

    public function store(OfferRequest $request)
    {
        $data = $this->service->create($request->validated());
        return WebResponse::response($data, 'backoffice.concierge.offer.index');
    }

    public function show($id)
    {
        $data = $this->service->find($id);
        return Inertia::render('concierge/offer/form', [
            'offer' => $data,
        ]);
    }

    public function update(OfferRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return WebResponse::response($data, 'backoffice.concierge.offer.index');
    }

    public function destroy($id)
    {
        $data = $this->service->destroy($id);
        return WebResponse::response($data, 'backoffice.concierge.offer.index');
    }

    public function destroy_bulk(Request $request)
    {
        $data = $this->service->bulkDeleteByIds($request->ids ?? []);
        return WebResponse::response($data, 'backoffice.concierge.offer.index');
    }
}
