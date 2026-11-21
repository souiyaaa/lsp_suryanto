<?php

namespace Tests\Unit\Services;

use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportService::class);
    }

    private function rental(string $start, string $end, int $price = 40000, ?User $user = null, ?Garage $garage = null): Rental
    {
        return Rental::factory()
            ->for($user ?? User::factory()->create())
            ->for($garage ?? Garage::factory()->create(['daily_price' => $price]))
            ->between($start, $end)
            ->create();
    }

    public function test_rent_of_a_segment(): void
    {
        $daily = $this->rental('2026-11-21', '2026-11-24');
        $monthly = Rental::factory()->bulanan()->for(Garage::factory()->create(['daily_price' => 40000]))->between('2026-11-21', '2026-12-21')->create();

        $this->assertSame(120000, $this->service->rentOf($daily));
        $this->assertSame(1000000, $this->service->rentOf($monthly)); // 25 x price
    }

    public function test_revenue_of_adds_extensions_on_their_own_garage_price_and_charging(): void
    {
        $car = Vehicle::factory()->mobilListrik()->create(['max_battery_kwh' => 60]);
        $electric = Garage::factory()->mobilListrik()->create(['daily_price' => 50000, 'charging_price_per_kwh' => 2500]);
        $original = Rental::factory()->for($car->user)->for($car, 'vehicle')->for($electric)->between('2026-11-21', '2026-11-24')->create(); // 150.000
        ChargingTransaction::factory()->create(['rental_id' => $original->id, 'current_kwh' => 15]);                                        // 112.500
        $other = Garage::factory()->mobilListrik()->create(['daily_price' => 60000, 'charging_price_per_kwh' => 4000]);
        $extension = Rental::factory()->extensionOf($original, 2)->create(['garage_id' => $other->id]);                                     // 120.000
        ChargingTransaction::factory()->create(['rental_id' => $extension->id, 'current_kwh' => 40]);                                       // 20 x 4.000 = 80.000

        $this->assertSame(150000 + 112500 + 120000 + 80000, $this->service->revenueOf($original->fresh()));
    }

    public function test_totals(): void
    {
        $this->rental('2026-11-05', '2026-11-08');
        $this->rental('2026-12-05', '2026-12-07');
        User::factory()->admin()->create();

        $this->assertSame(120000 + 80000, $this->service->totalRevenue());
        $this->assertSame(2, $this->service->totalPenyewa()); // the admin is not a penyewa
        $this->assertSame(0, app(ReportService::class)->revenuePerMonth(2030)->count());
    }

    public function test_revenue_per_month_only_counts_the_selected_year(): void
    {
        $this->rental('2026-11-05', '2026-11-08');           // 120.000
        $this->rental('2026-11-20', '2026-11-22');           // 80.000
        $this->rental('2026-12-01', '2026-12-02');           // 40.000
        $this->rental('2025-11-05', '2025-11-06');           // another year

        $perMonth = $this->service->revenuePerMonth(2026);

        $this->assertSame([11, 12], $perMonth->keys()->sort()->values()->all());
        $this->assertSame(['rental_count' => 2, 'revenue' => 200000], $perMonth[11]);
        $this->assertSame(['rental_count' => 1, 'revenue' => 40000], $perMonth[12]);
    }

    public function test_revenue_per_day_is_credited_to_the_rental_start_date_including_extensions(): void
    {
        $original = $this->rental('2026-11-05', '2026-11-08');           // 120.000, starts on the 5th
        Rental::factory()->extensionOf($original, 2)->create();           // 80.000, still credited to the 5th
        $this->rental('2026-11-09', '2026-11-10');                        // 40.000 on the 9th

        $perDay = $this->service->revenuePerDay(2026, 11);

        $this->assertSame(['rental_count' => 1, 'revenue' => 200000], $perDay[5]);
        $this->assertSame(['rental_count' => 1, 'revenue' => 40000], $perDay[9]);
        $this->assertArrayNotHasKey(8, $perDay->all()); // the extension's start day gets nothing
        $this->assertSame(0, $this->service->revenuePerDay(2026, 10)->count());
    }

    public function test_revenue_per_garage_type_and_per_penyewa(): void
    {
        $budi = User::factory()->create(['name' => 'Budi']);
        $sari = User::factory()->create(['name' => 'Sari']);
        $this->rental('2026-11-05', '2026-11-08', 40000, $budi);                                  // mobil 120.000
        $this->rental('2026-11-10', '2026-11-12', 40000, $budi);                                  // mobil 80.000
        $this->rental('2026-11-05', '2026-11-06', 10000, $sari, Garage::factory()->sepeda()->create(['daily_price' => 10000])); // sepeda 10.000

        $perType = $this->service->revenuePerGarageType();
        $perPenyewa = $this->service->revenuePerPenyewa();

        $this->assertSame(['rental_count' => 2, 'revenue' => 200000], $perType['mobil']);
        $this->assertSame(['rental_count' => 1, 'revenue' => 10000], $perType['sepeda']);
        $this->assertSame(['rental_count' => 2, 'revenue' => 200000], $perPenyewa['Budi']);
        $this->assertSame(['rental_count' => 1, 'revenue' => 10000], $perPenyewa['Sari']);
    }

    public function test_revenue_per_rental_has_one_row_per_original_rental(): void
    {
        $original = $this->rental('2026-11-05', '2026-11-08');
        Rental::factory()->extensionOf($original, 2)->create();

        $rows = $this->service->revenuePerRental();

        $this->assertCount(1, $rows);
        $this->assertSame(200000, $rows->first()['revenue']);
        $this->assertSame('2026-11-05', $rows->first()['start_date']->toDateString());
    }
}
