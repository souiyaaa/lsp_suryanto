<x-page title="Status Akun">
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        @php($status = auth()->user()->status)
        @if ($status === \App\Enums\UserStatus::Approved)
            <p class="text-green-700">Akun Anda sudah disetujui.</p>
            <a href="{{ route('rentals.index') }}" class="text-indigo-600 underline">Ke Sewa Saya</a>
        @elseif ($status === \App\Enums\UserStatus::Rejected)
            <p class="text-red-700 font-medium">Pendaftaran Anda ditolak. Anda tidak dapat menyewa garasi.</p>
        @else
            <p class="text-yellow-700 font-medium">Akun Anda sedang menunggu verifikasi admin. Anda belum dapat menyewa garasi.</p>
        @endif
    </div>
</x-page>
