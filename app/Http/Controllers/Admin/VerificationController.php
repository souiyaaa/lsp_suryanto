<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerificationRequest;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(private VerificationService $verificationService) {}

    public function index(): View
    {
        return view('admin.verifications.index', [
            'pendingPenyewa' => User::penyewa()->where('status', UserStatus::Pending)->oldest()->paginate(10),
        ]);
    }

    public function approve(VerificationRequest $request, User $user): RedirectResponse
    {
        $this->verificationService->approve($user);

        return back()->with('success', "{$user->name} disetujui.");
    }

    public function reject(User $user): RedirectResponse
    {
        $this->verificationService->reject($user);

        return back()->with('success', "{$user->name} ditolak.");
    }
}
