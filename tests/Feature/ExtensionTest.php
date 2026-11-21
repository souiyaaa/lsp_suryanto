<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksPaymentProofUploader;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use MocksPaymentProofUploader, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_tc_27_01_extension_list_shows_only_rentals_active_today(): void
    {
        $active = Rental::factory()->create();
        $ended = Rental::factory()->between(today()->subDays(9)->toDateString(), today()->subDays(5)->toDateString())->create();
        $future = Rental::factory()->between(today()->addDays(5)->toDateString(), today()->addDays(8)->toDateString())->create();

        $this->get('/admin/extensions')->assertOk()
            ->assertSee($active->penyewa->name)
            ->assertDontSee($ended->penyewa->name)
            ->assertDontSee($future->penyewa->name);
    }

    public function test_tc_28_01_extension_is_saved_on_the_same_garage(): void
    {
        $this->fakeUploader();
        $rental = Rental::factory()->create(); // today .. +3
        $this->get(route('admin.extensions.create', $rental))->assertOk();

        $preview = $this->getJson('/api/admin/extension-preview?'.http_build_query(['rental_id' => $rental->id, 'rental_type' => 'harian', 'number_of_days' => 4]))
            ->assertOk()->assertJsonPath('data.is_slot_changed', false)->assertJsonPath('data.fee', 'Rp 160.000');

        $this->post(route('admin.extensions.store', $rental), [
            'rental_type' => 'harian', 'number_of_days' => 4, 'garage_id' => $preview->json('data.garage_id'), 'payment_proof' => $this->proof(),
        ])->assertRedirect(route('admin.extensions.index'));

        $extension = Rental::where('parent_rental_id', $rental->id)->firstOrFail();
        $this->assertSame($rental->garage_id, $extension->garage_id);
        $this->assertSame($rental->end_date->toDateString(), $extension->start_date->toDateString());
        $this->assertSame(4, $extension->numberOfDays());
        $this->assertSame('https://example.test/proof.jpg', $extension->payment_proof_url);
    }

    public function test_tc_29_01_extension_follows_the_user_rules_and_needs_proof(): void
    {
        $this->fakeUploader();
        $rental = Rental::factory()->create();
        $garageId = $rental->garage_id;

        // Daily length is limited by the days-in-month of the segment start (end date of the rental).
        $this->post(route('admin.extensions.store', $rental), ['rental_type' => 'harian', 'number_of_days' => 31, 'garage_id' => $garageId, 'payment_proof' => $this->proof()])
            ->assertSessionHasErrors('number_of_days');
        $this->post(route('admin.extensions.store', $rental), ['rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $garageId])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, Rental::whereNotNull('parent_rental_id')->count());
    }

    public function test_tc_29_02_extension_of_an_inactive_or_extension_rental_is_refused(): void
    {
        $ended = Rental::factory()->between(today()->subDays(9)->toDateString(), today()->subDays(5)->toDateString())->create();
        $query = ['rental_type' => 'bulanan'];

        $this->getJson('/api/admin/extension-preview?'.http_build_query($query + ['rental_id' => $ended->id]))->assertStatus(422);

        $active = Rental::factory()->create();
        $extension = Rental::factory()->extensionOf($active)->create();
        $this->getJson('/api/admin/extension-preview?'.http_build_query($query + ['rental_id' => $extension->id]))->assertNotFound();
    }

    public function test_tc_31_01_extension_moves_to_the_cheapest_free_garage_on_a_slot_conflict(): void
    {
        $this->fakeUploader();
        $garage = Garage::factory()->create(['daily_price' => 40000]);
        $pricey = Garage::factory()->create(['daily_price' => 60000]);
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        $rental = Rental::factory()->for($garage)->create(); // today .. +3
        // Someone else already booked the same garage right after.
        Rental::factory()->for($garage)->between(today()->addDays(3)->toDateString(), today()->addDays(6)->toDateString())->create();

        $this->getJson('/api/admin/extension-preview?'.http_build_query(['rental_id' => $rental->id, 'rental_type' => 'harian', 'number_of_days' => 2]))
            ->assertOk()
            ->assertJsonPath('data.is_slot_changed', true)
            ->assertJsonPath('data.garage_code', $cheap->code)
            ->assertJsonPath('data.fee', 'Rp 60.000'); // 2 days x the NEW garage's price

        $this->post(route('admin.extensions.store', $rental), [
            'rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $cheap->id, 'payment_proof' => $this->proof(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($cheap->id, Rental::where('parent_rental_id', $rental->id)->first()->garage_id);
        $this->assertNotSame($pricey->id, $cheap->id);
    }

    public function test_tc_32_01_extension_is_blocked_when_no_garage_is_free(): void
    {
        $this->fakeUploader();
        $garage = Garage::factory()->create();
        $rental = Rental::factory()->for($garage)->create();
        Rental::factory()->for($garage)->between(today()->addDays(3)->toDateString(), today()->addDays(8)->toDateString())->create();

        $this->getJson('/api/admin/extension-preview?'.http_build_query(['rental_id' => $rental->id, 'rental_type' => 'harian', 'number_of_days' => 2]))
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->post(route('admin.extensions.store', $rental), [
            'rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $garage->id, 'payment_proof' => $this->proof(),
        ])->assertSessionHasErrors('rental_type');

        $this->assertSame(0, Rental::whereNotNull('parent_rental_id')->count());
    }

    public function test_tc_31_02_the_garage_confirmed_in_the_popup_is_the_one_saved(): void
    {
        $this->fakeUploader();
        $original = Garage::factory()->create(['daily_price' => 40000]);
        Garage::factory()->count(2)->create(['daily_price' => 30000]); // two equally cheap candidates
        $rental = Rental::factory()->for($original)->create();
        Rental::factory()->for($original)->between(today()->addDays(3)->toDateString(), today()->addDays(8)->toDateString())->create();

        $shown = $this->getJson('/api/admin/extension-preview?'.http_build_query(['rental_id' => $rental->id, 'rental_type' => 'harian', 'number_of_days' => 2]))
            ->assertJsonPath('data.is_slot_changed', true)->json('data.garage_id');

        $this->post(route('admin.extensions.store', $rental), [
            'rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $shown, 'payment_proof' => $this->proof(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($shown, Rental::where('parent_rental_id', $rental->id)->value('garage_id'));
    }

    public function test_tc_30_04_the_preview_warns_about_a_slot_change_with_the_new_price(): void
    {
        $original = Garage::factory()->create(['daily_price' => 40000]);
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        $rental = Rental::factory()->for($original)->create();
        Rental::factory()->for($original)->between(today()->addDays(3)->toDateString(), today()->addDays(8)->toDateString())->create();

        $this->getJson('/api/admin/extension-preview?'.http_build_query(['rental_id' => $rental->id, 'rental_type' => 'bulanan']))
            ->assertOk()
            ->assertJsonPath('data.is_slot_changed', true)
            ->assertJsonPath('data.garage_code', $cheap->code)
            ->assertJsonPath('data.daily_price', 'Rp 30.000')
            ->assertJsonPath('data.fee', 'Rp 750.000'); // 25 x 30.000
    }

    public function test_nfr_01_05_a_penyewa_cannot_extend_a_rental(): void
    {
        $this->fakeUploader();
        $rental = Rental::factory()->create();

        $this->actingAs($rental->penyewa)->post(route('admin.extensions.store', $rental), [
            'rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $rental->garage_id, 'payment_proof' => $this->proof(),
        ])->assertForbidden();

        $this->assertSame(0, Rental::whereNotNull('parent_rental_id')->count());
    }

    public function test_tc_33_01_my_rentals_shows_the_slot_change_notice(): void
    {
        $user = User::factory()->create();
        $first = Garage::factory()->create();
        $second = Garage::factory()->create();
        $original = Rental::factory()->for($user)->for($first)->create();
        Rental::factory()->extensionOf($original)->create(['garage_id' => $second->id]);

        $this->actingAs($user)->get('/rentals')->assertSee("slot Anda {$second->code}");
    }
}
