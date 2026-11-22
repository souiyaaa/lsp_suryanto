<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ChargingTransactionController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RentalPreviewController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', HomeRedirectController::class)->name('dashboard');
    Route::view('/status', 'penyewa.status')->name('status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('approved')->group(function () {
        Route::resource('vehicles', VehicleController::class)->only(['index', 'create', 'store']);
        Route::resource('rentals', RentalController::class)->only(['index', 'create', 'store']);
        Route::get('rentals/{rental}/charging/create', [ChargingTransactionController::class, 'create'])->name('charging.create');
        Route::post('rentals/{rental}/charging', [ChargingTransactionController::class, 'store'])->name('charging.store');

        // Under api/ so the exception handler answers in the JSON envelope.
        Route::prefix('api')->name('api.')->controller(RentalPreviewController::class)->group(function () {
            Route::get('garage-types', 'garageTypes')->name('garage-types');
            Route::get('available-garages', 'availableGarages')->name('available-garages');
            Route::get('rental-preview', 'rentalPreview')->name('rental-preview');
            Route::get('charging-preview', 'chargingPreview')->name('charging-preview');
        });
    });

    Route::middleware('admin')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('dashboard', Admin\AdminDashboardController::class)->name('dashboard');
            Route::get('verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
            Route::patch('verifications/{user}/approve', [Admin\VerificationController::class, 'approve'])->name('verifications.approve');
            Route::patch('verifications/{user}/reject', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');
            Route::resource('garages', Admin\GarageController::class)->only(['index', 'create', 'store']);
            Route::get('extensions', [Admin\ExtensionController::class, 'index'])->name('extensions.index');
            Route::get('extensions/{rental}/create', [Admin\ExtensionController::class, 'create'])->name('extensions.create');
            Route::post('extensions/{rental}', [Admin\ExtensionController::class, 'store'])->name('extensions.store');
            Route::get('reports', Admin\ReportController::class)->name('reports');
        });

        Route::get('api/admin/extension-preview', [Admin\ExtensionController::class, 'preview'])->name('api.admin.extension-preview');
    });
});

require __DIR__.'/auth.php';
