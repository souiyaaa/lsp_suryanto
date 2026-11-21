<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarageBatchTest extends TestCase
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

    public function test_tc_09_01_batch_creates_codes_and_prices(): void
    {
        $this->post('/admin/garages', ['type' => 'mobil_listrik', 'amount' => 3, 'daily_price' => 50000, 'charging_price_per_kwh' => 2500])
            ->assertRedirect(route('admin.garages.index'))->assertSessionHas('success', '3 garasi dibuat: E001–E003.');

        $this->assertSame(['E001', 'E002', 'E003'], Garage::orderBy('code')->pluck('code')->all());
        $this->assertSame(2500, Garage::first()->charging_price_per_kwh);

        $this->post('/admin/garages', ['type' => 'sepeda', 'amount' => 1, 'daily_price' => 10000, 'charging_price_per_kwh' => 999]);
        $this->assertNull(Garage::where('code', 'S001')->first()->charging_price_per_kwh);

        $this->get('/admin/garages')->assertOk()->assertSee('E003')->assertSee('Rp 50.000');
    }

    public function test_tc_10_01_batch_validation_and_capacity_error(): void
    {
        $this->post('/admin/garages', ['type' => 'mobil_listrik', 'amount' => 2, 'daily_price' => 50000])->assertSessionHasErrors('charging_price_per_kwh');
        $this->post('/admin/garages', ['type' => 'mobil', 'amount' => 1000, 'daily_price' => 40000])->assertSessionHasErrors('amount');

        Garage::factory()->count(5)->sequence(fn ($s) => ['code' => 'C'.str_pad((string) ($s->index + 1), 3, '0', STR_PAD_LEFT)])->create();
        $this->post('/admin/garages', ['type' => 'mobil', 'amount' => 995, 'daily_price' => 40000])->assertSessionHasErrors('amount');
        $this->assertSame(5, Garage::count());
    }

    public function test_tc_09_02_each_batch_keeps_its_own_price_and_numbering_continues(): void
    {
        $this->post('/admin/garages', ['type' => 'mobil', 'amount' => 2, 'daily_price' => 40000]);
        $this->post('/admin/garages', ['type' => 'mobil', 'amount' => 2, 'daily_price' => 55000]);

        $this->assertSame(['C001' => 40000, 'C002' => 40000, 'C003' => 55000, 'C004' => 55000], Garage::orderBy('code')->pluck('daily_price', 'code')->all());
    }

    public function test_nfr_01_04_a_penyewa_cannot_create_garages(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/garages', ['type' => 'mobil', 'amount' => 1, 'daily_price' => 40000])
            ->assertForbidden();

        $this->assertSame(0, Garage::count());
    }
}
