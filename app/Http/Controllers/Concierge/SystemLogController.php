<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\SystemLogContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class SystemLogController extends Controller
{
    protected SystemLogContract $service;

    public function __construct(SystemLogContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/system-log/index');
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
