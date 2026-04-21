<?php

namespace App\Http\Controllers\Api\Setting;

use App\Contract\Setting\SettingContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Utils\WebResponse;
use Spatie\QueryBuilder\AllowedFilter;

class SettingController extends Controller
{
    protected SettingContract $service;

    public function __construct(SettingContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $result = $this->service->all(
            allowedFilters: [
                AllowedFilter::partial('key'),
            ],
            allowedSorts: ['key', 'created_at'],
            withPaginate: true,
            perPage: (int) request()->get('per_page', 10),
            orderColumn: 'key',
            orderPosition: 'asc',
        );

        return WebResponse::json($result, 'Settings retrieved.');
    }

    public function show($id)
    {
        $result = $this->service->find($id);
        return WebResponse::json($result, 'Setting retrieved.');
    }

    public function update(SettingRequest $request, $id)
    {
        $result = $this->service->update($id, $request->validated());
        return WebResponse::json($result, 'Setting updated.');
    }
}
