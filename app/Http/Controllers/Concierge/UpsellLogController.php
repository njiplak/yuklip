<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\UpsellLogContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class UpsellLogController extends Controller
{
    protected UpsellLogContract $service;

    public function __construct(UpsellLogContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/upsell-log/index');
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
}
