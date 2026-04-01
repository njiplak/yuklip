<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\MenuItemContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\MenuItemRequest;
use App\Utils\WebResponse;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;

class MenuItemController extends Controller
{
    protected MenuItemContract $service;

    public function __construct(MenuItemContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/menu-item/index');
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
        return Inertia::render('concierge/menu-item/form');
    }

    public function store(MenuItemRequest $request)
    {
        $data = $this->service->create($request->validated());
        return WebResponse::response($data, 'backoffice.concierge.menu-item.index');
    }

    public function show($id)
    {
        $data = $this->service->find($id);
        return Inertia::render('concierge/menu-item/form', [
            'menuItem' => $data,
        ]);
    }

    public function update(MenuItemRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return WebResponse::response($data, 'backoffice.concierge.menu-item.index');
    }

    public function destroy($id)
    {
        $data = $this->service->destroy($id);
        return WebResponse::response($data, 'backoffice.concierge.menu-item.index');
    }

    public function destroy_bulk(Request $request)
    {
        $data = $this->service->bulkDeleteByIds($request->ids ?? []);
        return WebResponse::response($data, 'backoffice.concierge.menu-item.index');
    }
}
