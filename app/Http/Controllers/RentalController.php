<?php

namespace App\Http\Controllers;

use App\Enums\RentalType;
use App\Http\Requests\RentalRequest;
use App\Services\PaymentProofUploader;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function __construct(
        private RentalService $rentalService,
        private PaymentProofUploader $paymentProofUploader,
    ) {}

    /** My Rentals originals with their extensions and charging sessions. */
    public function index(Request $request): View
    {
        $rentals = $request->user()->rentals()->originals()
            ->with([
                'garage', 'vehicle', 'chargingTransactions.rental.vehicle', 'chargingTransactions.rental.garage',
                'extensions.garage', 'extensions.chargingTransactions.rental.vehicle', 'extensions.chargingTransactions.rental.garage',
            ])
            ->latest('start_date')
            ->paginate(10);

        return view('penyewa.rentals.index', ['rentals' => $rentals]);
    }

    public function create(Request $request): View
    {
        return view('penyewa.rentals.create', [
            'vehicles' => $request->user()->vehicles()->get(),
            'rentalTypes' => RentalType::cases(),
        ]);
    }

    public function store(RentalRequest $request): RedirectResponse
    {
        $paymentProofUrl = $this->paymentProofUploader->upload($request->file('payment_proof'));
        $this->rentalService->createRental($request->user(), $request->validated(), $paymentProofUrl);

        return redirect()->route('rentals.index')->with('success', 'Sewa berhasil dibuat.');
    }
}
