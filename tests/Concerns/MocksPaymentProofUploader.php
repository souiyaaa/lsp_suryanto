<?php

namespace Tests\Concerns;

use App\Services\PaymentProofUploader;
use Illuminate\Http\UploadedFile;

/** No test may call Cloudinary (NFR-14): feature tests that save a proof replace the uploader. */
trait MocksPaymentProofUploader
{
    private const PROOF_URL = 'https://example.test/proof.jpg';

    protected function fakeUploader(): void
    {
        $this->mock(PaymentProofUploader::class)
            ->shouldReceive('upload')
            ->andReturn(self::PROOF_URL);
    }

    protected function proof(): UploadedFile
    {
        return UploadedFile::fake()->image('bukti.jpg')->size(300);
    }
}
