<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_password' => 'secret-admin-pw']);
    }

    public function test_seeds_exactly_one_admin_who_can_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $admin = User::where('email', 'admin@garasi.test')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isApproved());

        $this->post('/login', ['email' => 'admin@garasi.test', 'password' => 'secret-admin-pw']);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_running_twice_keeps_one_admin_and_no_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['app.admin_password' => 'changed-pw']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertSame(3, User::count());
        $this->assertSame(9, Garage::count());
        $this->assertSame(4, Vehicle::count());

        $this->post('/login', ['email' => 'admin@garasi.test', 'password' => 'changed-pw']);
        $this->assertAuthenticated();
    }

    public function test_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $penyewa = User::where('email', 'penyewa@garasi.test')->firstOrFail();
        $this->assertSame(UserStatus::Approved, $penyewa->status);
        $this->assertCount(4, $penyewa->vehicles);
        $this->assertSame(UserStatus::Pending, User::where('email', 'pending@garasi.test')->firstOrFail()->status);
        $this->assertSame(3, Garage::where('type', 'mobil_listrik')->count());
        $this->assertSame(2, Garage::where('type', 'mobil_listrik')->distinct('daily_price')->count('daily_price'));
        $this->assertSame(0, Garage::where('is_used', true)->count());
    }

    public function test_requires_admin_password(): void
    {
        config(['app.admin_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }
}
