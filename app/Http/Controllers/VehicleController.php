<?php

namespace App\Http\Controllers;

use App\Enums\VehicleType;
use App\Http\Requests\VehicleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        return view('penyewa.vehicles.index', ['vehicles' => $request->user()->vehicles()->latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('penyewa.vehicles.create', ['vehicleTypes' => VehicleType::cases()]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        // Through the relation, so user_id is always the logged-in penyewa.
        $request->user()->vehicles()->create($request->validated());

        return redirect()->route('vehicles.index')->with('success', 'Kendaraan berhasil ditambahkan.');
    }
}
