<?php

namespace Tests\Unit\Services;

use App\Services\PaymentProofUploader;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentProofUploaderMockTest extends TestCase
{
    public function test_container_mock_replaces_uploader_so_no_internet_is_needed(): void
    {
        $this->mock(PaymentProofUploader::class)
            ->shouldReceive('upload')->once()->andReturn('https://example.test/proof.jpg');

        $url = app(PaymentProofUploader::class)->upload(UploadedFile::fake()->image('proof.jpg'));

        $this->assertSame('https://example.test/proof.jpg', $url);
    }
}
