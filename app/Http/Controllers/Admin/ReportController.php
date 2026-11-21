<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\ReportService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(ReportRequest $request, ReportService $reportService): View
    {
        $reportView = ReportView::tryFrom((string) $request->validated('view')) ?? ReportView::Monthly;
        $year = (int) ($request->validated('year') ?? today()->year);
        $month = (int) ($request->validated('month') ?? today()->month);

        $rows = match ($reportView) {
            ReportView::Monthly => $reportService->revenuePerMonth($year),
            ReportView::Daily => $reportService->revenuePerDay($year, $month),
            ReportView::GarageType => $reportService->revenuePerGarageType(),
            ReportView::Penyewa => $reportService->revenuePerPenyewa(),
        };

        return view('admin.reports', compact('reportView', 'year', 'month', 'rows'));
    }
}
