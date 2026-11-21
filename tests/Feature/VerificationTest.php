<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_tc_07_01_ktp_box_is_required_before_approving(): void
    {
        $user = User::factory()->pending()->create();

        $this->get('/admin/verifications')->assertOk()->assertSee($user->nik);

        $this->patch(route('admin.verifications.approve', $user))->assertSessionHasErrors('is_ktp_checked');
        $this->assertSame(UserStatus::Pending, $user->fresh()->status);

        $this->patch(route('admin.verifications.approve', $user), ['is_ktp_checked' => '1'])->assertSessionHas('success');
        $this->assertSame(UserStatus::Approved, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->ktp_verified_at);
    }

    public function test_tc_08_01_reject_and_no_second_verification(): void
    {
        $user = User::factory()->pending()->create();

        $this->patch(route('admin.verifications.reject', $user))->assertSessionHas('success');
        $this->assertSame(UserStatus::Rejected, $user->fresh()->status);

        $this->patch(route('admin.verifications.approve', $user), ['is_ktp_checked' => '1'])->assertSessionHasErrors('status');
        $this->assertSame(UserStatus::Rejected, $user->fresh()->status);
    }

    public function test_tc_07_02_only_pending_penyewa_are_listed(): void
    {
        $pending = User::factory()->pending()->create(['name' => 'Menunggu Budi']);
        $approved = User::factory()->create(['name' => 'Sudah Sari']);
        $rejected = User::factory()->rejected()->create(['name' => 'Ditolak Dedi']);

        $this->get('/admin/verifications')->assertOk()
            ->assertSee($pending->name)
            ->assertDontSee($approved->name)
            ->assertDontSee($rejected->name);
    }

    public function test_tc_07_03_an_approved_penyewa_can_now_open_the_rental_pages(): void
    {
        $user = User::factory()->pending()->create();
        $this->actingAs($user)->get('/rentals')->assertRedirect(route('status'));

        $this->actingAs($this->admin)->patch(route('admin.verifications.approve', $user), ['is_ktp_checked' => '1']);

        $this->actingAs($user->fresh())->get('/rentals')->assertOk();
    }

    public function test_tc_08_02_a_rejected_penyewa_stays_locked_out(): void
    {
        $user = User::factory()->pending()->create();

        $this->patch(route('admin.verifications.reject', $user));

        $this->actingAs($user->fresh())->get('/rentals')->assertRedirect(route('status'));
    }

    public function test_nfr_01_03_a_penyewa_cannot_verify_anyone(): void
    {
        $target = User::factory()->pending()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.verifications.approve', $target), ['is_ktp_checked' => '1'])
            ->assertForbidden();

        $this->assertSame(UserStatus::Pending, $target->fresh()->status);
    }
}
