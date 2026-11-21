<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function approve(User $penyewa): void
    {
        $this->ensurePending($penyewa);
        $penyewa->forceFill(['status' => UserStatus::Approved, 'ktp_verified_at' => now()])->save();
    }

    public function reject(User $penyewa): void
    {
        $this->ensurePending($penyewa);
        $penyewa->forceFill(['status' => UserStatus::Rejected])->save();
    }

    private function ensurePending(User $penyewa): void
    {
        if ($penyewa->status !== UserStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Pengguna ini sudah diverifikasi.']);
        }
    }
}