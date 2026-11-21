<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\ReportService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(ReportService $reportService): View
    {
        return view('admin.dashboard', [
            'totalRevenue' => $reportService->totalRevenue(),
            'totalPenyewa' => $reportService->totalPenyewa(),
            'garagesByType' => Garage::orderBy('code')->get()->groupBy(fn (Garage $garage) => $garage->type->label()),
        ]);
    }
}
