<?php

namespace App\Providers;

use App\Services\PaymentProofUploader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Built lazily so Cloudinary is only configured when an upload happens.
        $this->app->bind(PaymentProofUploader::class, fn () => new PaymentProofUploader);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
