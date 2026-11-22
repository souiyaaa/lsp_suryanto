<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationAndWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_welcome_page_with_login_and_register(): void
    {
        $this->get('/')->assertOk()->assertSee(route('login'))->assertSee(route('register'));
    }

    public function test_logged_in_user_is_sent_from_welcome_to_their_home(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_my_vehicles_shows_ten_per_page(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->count(11)->for($user)->create(['type' => 'mobil']);

        $this->actingAs($user)->get('/vehicles')->assertViewHas('vehicles', fn ($page) => $page->count() === 10 && $page->total() === 11);
        $this->actingAs($user)->get('/vehicles?page=2')->assertViewHas('vehicles', fn ($page) => $page->count() === 1);
    }

    public function test_admin_garage_list_shows_ten_per_page(): void
    {
        Garage::factory()->count(12)->create();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/garages')
            ->assertViewHas('garages', fn ($page) => $page->count() === 10 && $page->total() === 12);
    }

    public function test_report_rows_are_paged_and_keep_their_keys(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/reports?view=per_penyewa')
            ->assertOk()->assertViewHas('rows', fn ($page) => $page->total() === 0);
    }

    public function test_forbidden_page_uses_the_403_error_layout(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')
            ->assertForbidden()->assertSee('Access denied');
    }
}
