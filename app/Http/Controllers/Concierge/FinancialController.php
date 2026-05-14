<?php

namespace App\Http\Controllers\Concierge;

use App\Http\Controllers\Api\Concierge\FinancialReportController;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class FinancialController extends Controller
{
    public function index()
    {
        return Inertia::render('concierge/financial/index');
    }

    public function fetch(FinancialReportController $api)
    {
        return $api->index();
    }
}
