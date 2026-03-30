<?php

namespace App\Http\Controllers\Concierge;

use App\Contract\Concierge\TransactionContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concierge\TransactionRequest;
use App\Utils\WebResponse;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    protected TransactionContract $service;

    public function __construct(TransactionContract $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return Inertia::render('concierge/transaction/index');
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
        return Inertia::render('concierge/transaction/form');
    }

    public function store(TransactionRequest $request)
    {
        $data = $this->service->create($request->validated());
        return WebResponse::response($data, 'backoffice.concierge.transaction.index');
    }

    public function show($id)
    {
        $data = $this->service->find($id);
        return Inertia::render('concierge/transaction/form', [
            'transaction' => $data,
        ]);
    }

    public function update(TransactionRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return WebResponse::response($data, 'backoffice.concierge.transaction.index');
    }

    public function destroy($id)
    {
        $data = $this->service->destroy($id);
        return WebResponse::response($data, 'backoffice.concierge.transaction.index');
    }

    public function destroy_bulk(Request $request)
    {
        $data = $this->service->bulkDeleteByIds($request->ids ?? []);
        return WebResponse::response($data, 'backoffice.concierge.transaction.index');
    }
}
