<?php

namespace Tests\Unit\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_sets_status_and_verification_time(): void
    {
        $user = User::factory()->pending()->create();

        app(VerificationService::class)->approve($user);

        $this->assertSame(UserStatus::Approved, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->ktp_verified_at);
    }

    public function test_reject_sets_status_without_verification_time(): void
    {
        $user = User::factory()->pending()->create(['ktp_verified_at' => null]);

        app(VerificationService::class)->reject($user);

        $this->assertSame(UserStatus::Rejected, $user->fresh()->status);
        $this->assertNull($user->fresh()->ktp_verified_at);
    }

    public function test_only_pending_users_can_be_verified(): void
    {
        $service = app(VerificationService::class);

        foreach ([User::factory()->create(), User::factory()->rejected()->create()] as $user) {
            foreach (['approve', 'reject'] as $action) {
                try {
                    $service->{$action}($user);
                    $this->fail("{$action} should have been refused");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('status', $e->errors());
                }
            }
        }

        $this->assertSame(UserStatus::Approved, User::where('status', 'approved')->first()->status);
        $this->assertSame(1, User::where('status', 'rejected')->count());
    }
}
