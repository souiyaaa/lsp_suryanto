<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

        $rows = $this->paginate($rows->sortKeys(), $request);

        return view('admin.reports', compact('reportView', 'year', 'month', 'rows'));
    }

    /** The report rows are already a keyed Collection, so page it by hand and keep the keys (month, day, name). */
    private function paginate(Collection $rows, Request $request, int $perPage = 10): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($rows->forPage($page, $perPage), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
