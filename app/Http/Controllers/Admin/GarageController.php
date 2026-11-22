<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarageBatchRequest;
use App\Models\Garage;
use App\Services\GarageBatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GarageController extends Controller
{
    public function __construct(private GarageBatchService $garageBatchService) {}

    public function index(): View
    {
        return view('admin.garages.index', ['garages' => Garage::orderBy('type')->orderBy('code')->paginate(10)]);
    }

    public function create(): View
    {
        return view('admin.garages.create', ['garageTypes' => GarageType::cases()]);
    }

    public function store(GarageBatchRequest $request): RedirectResponse
    {
        $createdGarages = $this->garageBatchService->createBatch(
            GarageType::from($request->validated('type')),
            (int) $request->validated('amount'),
            (int) $request->validated('daily_price'),
            $request->validated('charging_price_per_kwh')
        );

        return redirect()->route('admin.garages.index')
            ->with('success', "{$createdGarages->count()} garasi dibuat: {$createdGarages->first()->code}–{$createdGarages->last()->code}.");
    }
}
