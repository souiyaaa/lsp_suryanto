<x-page title="Verifikasi Penyewa">
    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left"><tr>
                <th class="p-3">Nama</th><th class="p-3">NIK</th><th class="p-3">Telepon</th><th class="p-3">Email</th><th class="p-3">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($pendingPenyewa as $user)
                <tr class="border-t align-top">
                    <td class="p-3">{{ $user->name }}</td>
                    <td class="p-3">{{ $user->nik }}</td>
                    <td class="p-3">{{ $user->phone }}</td>
                    <td class="p-3">{{ $user->email }}</td>
                    <td class="p-3">
                        <form method="POST" action="{{ route('admin.verifications.approve', $user) }}" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <label class="flex items-center gap-1"><input type="checkbox" name="is_ktp_checked" value="1"> KTP sudah dicek</label>
                            <button class="px-3 py-1 bg-green-600 text-white rounded">Setujui</button>
                        </form>
                        @error('is_ktp_checked')<p class="text-red-600 text-xs mt-1">Centang "KTP sudah dicek" dulu.</p>@enderror
                        <form method="POST" action="{{ route('admin.verifications.reject', $user) }}" class="mt-2">
                            @csrf @method('PATCH')
                            <button class="px-3 py-1 bg-red-600 text-white rounded">Tolak</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-4 text-gray-500">Tidak ada penyewa yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-page>
