<?php

namespace App\Services;

use App\Exceptions\PaymentProofUploadException;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * The only place the app talks to Cloudinary. Callers store the returned URL
 * in rentals.payment_proof_url / charging_trx.payment_proof_url.
 * Call upload() BEFORE opening the DB transaction.
 */
class PaymentProofUploader
{
    private const FOLDER = 'garage-rental/payment-proofs';

    /** Shared with FormRequests (NFR-04). */
    public const MAX_PROOF_SIZE_KB = 2048;

    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private ?UploadApi $api = null) {}

    /**
     * @throws PaymentProofUploadException on missing config, network or API errors
     */
    public function upload(UploadedFile $paymentProof): string
    {
        try {
            $result = $this->api()->upload($paymentProof->getRealPath(), [
                'folder' => self::FOLDER,
                'resource_type' => 'image',
            ]);

            $url = $result['secure_url'] ?? null;
        } catch (Throwable $e) {
            report($e);
            throw new PaymentProofUploadException(previous: $e);
        }

        if (! is_string($url) || $url === '') {
            throw new PaymentProofUploadException;
        }

        return $url;
    }

    private function api(): UploadApi
    {
        if ($this->api) {
            return $this->api;
        }

        $url = config('services.cloudinary.url');

        if (! $url) {
            throw new \RuntimeException('CLOUDINARY_URL is not configured.');
        }

        return $this->api = (new UploadApi(Configuration::instance($url)));
    }
}
