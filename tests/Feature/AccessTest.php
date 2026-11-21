<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    public function test_tc_04_01_guest_is_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/rentals')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_tc_04_02_pending_and_rejected_users_only_see_their_status_page(): void
    {
        foreach ([User::factory()->pending()->create(), User::factory()->rejected()->create()] as $user) {
            $this->actingAs($user);

            $this->get('/status')->assertOk();
            $this->get('/rentals')->assertRedirect(route('status'));
            $this->get('/rentals/create')->assertRedirect(route('status'));
            $this->get('/vehicles')->assertRedirect(route('status'));
            $this->get('/api/garage-types')->assertRedirect(route('status'));
        }
    }

    public function test_tc_04_03_status_page_shows_the_right_message(): void
    {
        $this->actingAs(User::factory()->pending()->create())->get('/status')->assertSee('menunggu verifikasi');
        $this->actingAs(User::factory()->rejected()->create())->get('/status')->assertSee('ditolak');
    }

    public function test_tc_04_04_dashboard_sends_each_role_to_its_home(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect(route('rentals.index'));
        $this->actingAs(User::factory()->pending()->create())->get('/dashboard')->assertRedirect(route('status'));
        $this->actingAs(User::factory()->rejected()->create())->get('/dashboard')->assertRedirect(route('status'));
    }

    public function test_tc_04_05_approved_penyewa_can_open_their_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/rentals', '/rentals/create', '/vehicles', '/vehicles/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_nfr_01_01_penyewa_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/admin/dashboard', '/admin/verifications', '/admin/garages', '/admin/garages/create',
            '/admin/extensions', '/admin/reports', '/api/admin/extension-preview'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_nfr_01_02_admin_can_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['/admin/dashboard', '/admin/verifications', '/admin/garages', '/admin/garages/create',
            '/admin/extensions', '/admin/reports'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_nfr_02_01_find_own_rental_only_finds_own_original_rentals(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $original = \App\Models\Rental::factory()->create(['user_id' => $owner->id]);
        $extension = \App\Models\Rental::factory()->extensionOf($original)->create();

        $this->assertTrue($owner->findOwnRental($original->id)->is($original));

        foreach ([[$other, $original->id], [$owner, $extension->id], [$owner, 9999]] as [$user, $id]) {
            try {
                $user->findOwnRental($id);
                $this->fail('Expected a 404');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
