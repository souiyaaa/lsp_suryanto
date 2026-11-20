{{-- Usage: openConfirm('Delete this participant?', () => deleteParticipant(id)) --}}
<div id="confirm-modal" class="hidden fixed inset-0 bg-black/40 items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-lg">
        <p id="confirm-message" class="mb-6">Are you sure?</p>
        <div class="flex justify-end gap-3">
            <button type="button" onclick="closeConfirm()" class="px-4 py-2 rounded border">Cancel</button>
            <button type="button" id="confirm-yes" class="px-4 py-2 rounded bg-red-600 text-white">Delete</button>
        </div>
    </div>
</div>