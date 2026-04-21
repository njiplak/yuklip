<?php

namespace App\Http\Controllers\Api\Concierge;

use App\Contract\Concierge\OfferContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\OfferRequest;
use App\Utils\WebResponse;
use Spatie\QueryBuilder\AllowedFilter;

class OfferController extends Controller
{
    protected OfferContract $service;

    public function __construct(OfferContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $result = $this->service->all(
            allowedFilters: [
                AllowedFilter::exact('category'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('title'),
            ],
            allowedSorts: ['price', 'title', 'created_at'],
            withPaginate: true,
            perPage: (int) request()->get('per_page', 10),
        );

        return WebResponse::json($result, 'Offers retrieved.');
    }

    public function show($id)
    {
        $result = $this->service->find($id);
        return WebResponse::json($result, 'Offer retrieved.');
    }

    public function store(OfferRequest $request)
    {
        $result = $this->service->create($request->validated());
        return WebResponse::json($result, 'Offer created.', 201);
    }

    public function update(OfferRequest $request, $id)
    {
        $result = $this->service->update($id, $request->validated());
        return WebResponse::json($result, 'Offer updated.');
    }
}
