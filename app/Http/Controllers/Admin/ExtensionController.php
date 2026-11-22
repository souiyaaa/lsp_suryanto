<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RentalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtensionPreviewRequest;
use App\Http\Requests\Admin\ExtensionRequest;
use App\Models\Rental;
use App\Services\ExtensionService;
use App\Services\PaymentProofUploader;
use App\Support\DateFormatter;
use App\Support\Rupiah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExtensionController extends Controller
{
    public function __construct(
        private ExtensionService $extensionService,
        private PaymentProofUploader $paymentProofUploader,
    ) {}

    public function index(): View
    {
        return view('admin.extensions.index', ['rentals' => $this->extensionService->paginateActiveRentalsToday()]);
    }

    public function create(Rental $rental): View
    {
        return view('admin.extensions.create', [
            'rental' => $rental,
            'currentSegment' => $this->extensionService->currentSegment($rental),
            'rentalTypes' => RentalType::cases(),
        ]);
    }

    /** Feeds the confirmation popup: new garage, price and fee before saving (FR-30). */
    public function preview(ExtensionPreviewRequest $request): JsonResponse
    {
        $preview = $this->extensionService->previewExtension(
            Rental::findOrFail($request->validated('rental_id')),
            RentalType::from($request->validated('rental_type')),
            $request->validated('number_of_days')
        );

        return $this->successResponse([
            'start_date' => DateFormatter::format($preview['start_date']),
            'end_date' => DateFormatter::format($preview['end_date']),
            'garage_id' => $preview['garage']->id,
            'garage_code' => $preview['garage']->code,
            'daily_price' => Rupiah::format($preview['garage']->daily_price),
            'fee' => Rupiah::format($preview['fee']),
            'is_slot_changed' => $preview['is_slot_changed'],
        ]);
    }

    public function store(ExtensionRequest $request, Rental $rental): RedirectResponse
    {
        $paymentProofUrl = $this->paymentProofUploader->upload($request->file('payment_proof'));
        $this->extensionService->extend($rental, $request->validated(), $paymentProofUrl);

        return redirect()->route('admin.extensions.index')->with('success', 'Perpanjangan tersimpan.');
    }
}
