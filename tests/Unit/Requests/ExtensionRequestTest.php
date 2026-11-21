<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\ExtensionPreviewRequest;
use App\Http\Requests\Admin\ExtensionRequest;
use App\Models\Garage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExtensionRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(string $requestClass, array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, (new $requestClass)->rules());
    }

    private function valid(): array
    {
        return [
            'rental_type' => 'harian',
            'number_of_days' => 5,
            'garage_id' => Garage::factory()->create()->id,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg')->size(300),
        ];
    }

    public function test_valid_extension_passes(): void
    {
        $this->assertFalse($this->validate(ExtensionRequest::class, $this->valid())->fails());
    }

    public function test_bulanan_does_not_need_days(): void
    {
        $data = ['rental_type' => 'bulanan', 'number_of_days' => null] + $this->valid();

        $this->assertFalse($this->validate(ExtensionRequest::class, $data)->fails());
    }

    public function test_harian_requires_days_and_a_known_rental_type(): void
    {
        $noDays = ['number_of_days' => null] + $this->valid();
        $badType = ['rental_type' => 'mingguan'] + $this->valid();

        $this->assertArrayHasKey('number_of_days', $this->validate(ExtensionRequest::class, $noDays)->errors()->toArray());
        $this->assertArrayHasKey('rental_type', $this->validate(ExtensionRequest::class, $badType)->errors()->toArray());
        $this->assertArrayHasKey('number_of_days', $this->validate(ExtensionRequest::class, ['number_of_days' => 0] + $this->valid())->errors()->toArray());
    }

    public function test_garage_must_exist(): void
    {
        $errors = $this->validate(ExtensionRequest::class, ['garage_id' => 9999] + $this->valid())->errors()->toArray();

        $this->assertArrayHasKey('garage_id', $errors);
    }

    public function test_payment_proof_is_required_and_limited_to_images_under_2mb(): void
    {
        $missing = $this->valid();
        unset($missing['payment_proof']);

        $this->assertArrayHasKey('payment_proof', $this->validate(ExtensionRequest::class, $missing)->errors()->toArray());
        $this->assertArrayHasKey('payment_proof', $this->validate(ExtensionRequest::class, ['payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')] + $this->valid())->errors()->toArray());
        $this->assertArrayHasKey('payment_proof', $this->validate(ExtensionRequest::class, ['payment_proof' => UploadedFile::fake()->image('big.jpg')->size(2049)] + $this->valid())->errors()->toArray());
        $this->assertFalse($this->validate(ExtensionRequest::class, ['payment_proof' => UploadedFile::fake()->image('ok.webp')->size(2048)] + $this->valid())->fails());
    }

    public function test_preview_needs_rental_but_not_garage_or_proof(): void
    {
        $rules = (new ExtensionPreviewRequest)->rules();

        $this->assertArrayNotHasKey('payment_proof', $rules);
        $this->assertArrayNotHasKey('garage_id', $rules);
        $this->assertArrayHasKey('rental_id', $rules);

        $ok = Validator::make(['rental_id' => 1, 'rental_type' => 'bulanan'], $rules);
        $missingRental = Validator::make(['rental_type' => 'bulanan'], $rules);

        $this->assertFalse($ok->fails());
        $this->assertArrayHasKey('rental_id', $missingRental->errors()->toArray());
    }
}
