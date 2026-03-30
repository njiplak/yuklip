<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\WebhookLogContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class WebhookLogController extends Controller
{
    protected WebhookLogContract $service;

    public function __construct(WebhookLogContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/webhook-log/index');
    }

    public function fetch()
    {
        $data = $this->service->all(
            allowedFilters: [],
            allowedSorts: [],
            withPaginate: true,
            perPage: request()->get('per_page', 10),
            orderColumn: 'id',
            orderPosition: 'desc',
        );
        return response()->json($data);
    }
}
