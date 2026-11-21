<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'nik' => '3171234567890001',
            'phone' => '081234567890',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_tc_01_01_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_tc_01_02_new_users_can_register_as_pending_penyewa(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isApproved());
    }

    public function test_tc_01_03_role_and_status_cannot_be_injected(): void
    {
        $this->post('/register', $this->payload(['role' => 'admin', 'status' => 'approved']));

        $user = User::firstOrFail();
        $this->assertSame(UserRole::Penyewa, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_tc_01_04_nik_must_be_16_digits(): void
    {
        foreach (['123', '31712345678900012', '317123456789000a'] as $nik) {
            $this->post('/register', $this->payload(['nik' => $nik]))->assertSessionHasErrors('nik');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_01_05_phone_is_required(): void
    {
        $this->post('/register', $this->payload(['phone' => '']))->assertSessionHasErrors('phone');
    }

    public function test_tc_02_01_nik_and_email_must_be_unique(): void
    {
        User::factory()->create(['nik' => '3171234567890001', 'email' => 'test@example.com']);

        $this->post('/register', $this->payload())->assertSessionHasErrors(['nik', 'email']);
    }

    public function test_tc_02_02_rejected_nik_cannot_register_again(): void
    {
        User::factory()->rejected()->create(['nik' => '3171234567890001']);

        $this->post('/register', $this->payload(['email' => 'new@example.com']))
            ->assertSessionHasErrors('nik');
    }
}
