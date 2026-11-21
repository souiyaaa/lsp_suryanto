<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargingRequest;
use App\Services\ChargingService;
use App\Services\PaymentProofUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingTransactionController extends Controller
{
    public function __construct(
        private ChargingService $chargingService,
        private PaymentProofUploader $paymentProofUploader,
    ) {
    }

    // {rental} in the route arrives here as $rentalId (route parameters fill scalar arguments in order).
    public function create(Request $request, int $rentalId): View
    {
        $rental = $request->user()->findOwnRental($rentalId);
        $activeSegment = $this->chargingService->findActiveSegment($rental);

        
        abort_unless($activeSegment?->garage->type->hasCharging(), 404);

        return view('penyewa.charging.create', ['rental' => $rental, 'activeSegment' => $activeSegment]);
    }

    public function store(ChargingRequest $request, int $rentalId): RedirectResponse
    {
        $rental = $request->user()->findOwnRental($rentalId);

        $paymentProofUrl = $this->paymentProofUploader->upload($request->file('payment_proof'));
        $this->chargingService->addSession($rental, (float) $request->validated('current_kwh'), $paymentProofUrl);

        return redirect()->route('rentals.index')->with('success', 'Pembayaran pengecasan tersimpan.');
    }
}
