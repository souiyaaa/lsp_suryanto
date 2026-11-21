<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_and_helpers(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create();

        $this->assertInstanceOf(UserRole::class, $admin->role);
        $this->assertInstanceOf(UserStatus::class, $pending->status);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isApproved());
        $this->assertFalse($pending->isAdmin());
        $this->assertFalse($pending->isApproved());
    }
}
