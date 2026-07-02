<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        return view('dashboard', [
            'summary' => $this->dashboard->summaryForUser($request->user()),
        ]);
    }
}
