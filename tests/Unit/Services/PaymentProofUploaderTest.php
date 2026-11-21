<?php

namespace Tests\Unit\Services;

use App\Exceptions\PaymentProofUploadException;
use App\Services\PaymentProofUploader;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentProofUploaderTest extends TestCase
{
    public function test_upload_returns_secure_url_and_uses_proof_folder(): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('upload')
            ->once()
            ->withArgs(fn ($path, $opts) => $opts['folder'] === 'garage-rental/payment-proofs')
            ->andReturn(new ApiResponse(['secure_url' => 'https://res.cloudinary.com/demo/image/upload/proof.jpg'], []));

        $url = (new PaymentProofUploader($api))->upload(UploadedFile::fake()->image('proof.jpg'));

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/proof.jpg', $url);
    }

    public function test_network_error_becomes_user_facing_exception(): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('upload')->andThrow(new RuntimeException('cURL error'));

        $this->expectException(PaymentProofUploadException::class);
        $this->expectExceptionMessage('Gagal mengunggah bukti pembayaran, coba lagi');

        (new PaymentProofUploader($api))->upload(UploadedFile::fake()->image('proof.jpg'));
    }

    public function test_response_without_url_is_rejected(): void
    {
        $api = Mockery::mock(UploadApi::class);
        $api->shouldReceive('upload')->andReturn(new ApiResponse([], []));

        $this->expectException(PaymentProofUploadException::class);

        (new PaymentProofUploader($api))->upload(UploadedFile::fake()->image('proof.jpg'));
    }

    public function test_missing_cloudinary_url_throws_without_calling_the_internet(): void
    {
        config(['services.cloudinary.url' => null]);

        try {
            (new PaymentProofUploader)->upload(UploadedFile::fake()->image('proof.jpg'));
            $this->fail('Expected exception');
        } catch (PaymentProofUploadException $e) {
            $this->assertSame('CLOUDINARY_URL is not configured.', $e->getPrevious()->getMessage());
        }
    }

    public function test_validation_constants(): void
    {
        $this->assertSame(2048, PaymentProofUploader::MAX_PROOF_SIZE_KB);
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp'], PaymentProofUploader::ALLOWED_MIMES);
    }
}
