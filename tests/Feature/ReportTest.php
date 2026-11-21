<?php

namespace Tests\Feature;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_tc_36_01_dashboard_shows_totals_and_slot_status(): void
    {
        $used = Garage::factory()->create(['is_used' => true]);
        $free = Garage::factory()->create();
        Rental::factory()->for($used)->create(); // today .. +3 days, Rp 40.000 x 3

        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('Rp 120.000')
            ->assertSee("{$used->code} terpakai")
            ->assertSee("{$free->code} kosong");
    }

    public function test_tc_37_01_reports_for_every_view(): void
    {
        $penyewa = User::factory()->create(['name' => 'Budi Santoso']);
        Rental::factory()->for($penyewa)->for(Garage::factory()->create(['daily_price' => 40000]))
            ->between('2026-11-05', '2026-11-08')->create(); // Rp 120.000

        $this->get('/admin/reports')->assertOk()->assertSee('November')->assertSee('Rp 120.000');
        $this->get('/admin/reports?view=per_month&year=2026')->assertOk()->assertSee('Rp 120.000');
        $this->get('/admin/reports?view=per_month&year=2025')->assertOk()->assertSee('Belum ada data');
        $this->get('/admin/reports?view=per_day&year=2026&month=11')->assertOk()->assertSee('5 November 2026')->assertSee('Rp 120.000');
        $this->get('/admin/reports?view=per_garage_type')->assertOk()->assertSee('Mobil (Bensin)')->assertSee('Rp 120.000');
        $this->get('/admin/reports?view=per_penyewa')->assertOk()->assertSee('Budi Santoso')->assertSee('Rp 120.000');
    }

    public function test_tc_38_01_report_filters_are_validated(): void
    {
        $this->get('/admin/reports?view=weekly')->assertSessionHasErrors('view');
        $this->get('/admin/reports?month=13')->assertSessionHasErrors('month');
        $this->get('/admin/reports?year=1999')->assertSessionHasErrors('year');
    }

    public function test_tc_37_02_extension_and_charging_are_credited_to_the_original_start_date(): void
    {
        $car = Vehicle::factory()->mobilListrik()->create(['max_battery_kwh' => 60]);
        $electric = Garage::factory()->mobilListrik()->create(['daily_price' => 50000, 'charging_price_per_kwh' => 2500]);
        $original = Rental::factory()->for($car->user)->for($car, 'vehicle')->for($electric)->between('2026-11-05', '2026-11-08')->create(); // 150.000
        ChargingTransaction::factory()->create(['rental_id' => $original->id, 'current_kwh' => 15]);                                         // 112.500
        Rental::factory()->extensionOf($original, 2)->create();                                                                             // 100.000, starts 8 Nov

        $this->get('/admin/reports?view=per_day&year=2026&month=11')->assertOk()
            ->assertSee('5 November 2026')
            ->assertSee('Rp 362.500')
            ->assertDontSee('8 November 2026'); // the extension's own start day gets no row

        $this->get('/admin/reports?view=per_month&year=2026')->assertSee('Rp 362.500');
    }

    public function test_tc_37_03_an_empty_month_shows_no_data(): void
    {
        Rental::factory()->between('2026-11-05', '2026-11-08')->create();

        $this->get('/admin/reports?view=per_day&year=2026&month=10')->assertOk()->assertSee('Belum ada data');
        $this->get('/admin/reports?view=per_month&year=2025')->assertOk()->assertSee('Belum ada data');
    }

    public function test_nfr_01_06_a_penyewa_cannot_open_reports(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();
    }
}
