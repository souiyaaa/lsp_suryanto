// Thin wrapper so every page reads the same { success, message, data, errors } envelope.
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

async function apiRequest(url, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
        },
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        return { success: false, message: 'Cannot reach the server', data: null, errors: null };
    }
}

function showAlert(message, isSuccess = true) {
    const alertBox = document.getElementById('alert-box');
    alertBox.textContent = message;
    alertBox.className = 'mb-4 px-4 py-3 rounded text-sm '
        + (isSuccess ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
}

function openConfirm(message, onConfirm) {
    const modal = document.getElementById('confirm-modal');
    document.getElementById('confirm-message').textContent = message;
    document.getElementById('confirm-yes').onclick = () => {
        closeConfirm();
        onConfirm();
    };
    modal.classList.replace('hidden', 'flex');
}

function closeConfirm() {
    document.getElementById('confirm-modal').classList.replace('flex', 'hidden');
}