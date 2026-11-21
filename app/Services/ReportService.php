<?php
namespace App\Services;

use App\Services\Calculators\RentalCalculator;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private RentalCalculator $rentalCalculator,
        private ChargingService $chargingService,
    ) {
    }

    /** @return Collection<int, array{start_date: \Carbon\CarbonImmutable, garage_type: \App\Enums\GarageType, penyewa: User, revenue: int}> */
    public function revenuePerRental(): Collection
    {
        return Rental::originals()
            ->with([
                'penyewa', 'garage', 'vehicle',
                'chargingTransactions.rental.garage', 'chargingTransactions.rental.vehicle',
                'extensions.garage', 'extensions.vehicle',
                'extensions.chargingTransactions.rental.garage', 'extensions.chargingTransactions.rental.vehicle',
            ])
            ->get()
            ->map(fn (Rental $originalRental) => [
                'start_date' => $originalRental->start_date,
                'garage_type' => $originalRental->garage->type,
                'penyewa' => $originalRental->penyewa,
                'revenue' => $this->revenueOf($originalRental),
            ]);
    }

    public function revenueOf(Rental $originalRental): int
    {
        $segments = collect([$originalRental])->concat($originalRental->extensions);

        return $segments->sum(fn (Rental $segment) => $this->rentOf($segment) + $this->chargingRevenueOf($segment));
    }

    public function rentOf(Rental $segment): int
    {
        return $this->rentalCalculator->calculateRent($segment->rental_type, $segment->garage->daily_price, $segment->numberOfDays());
    }

    public function totalRevenue(): int
    {
        return $this->revenuePerRental()->sum('revenue');
    }

    public function totalPenyewa(): int
    {
        return User::penyewa()->count();
    }

    /** @return Collection<int, array{rental_count: int, revenue: int}> keyed by month 1–12 */
    public function revenuePerMonth(int $year): Collection
    {
        $rentalsInYear = $this->revenuePerRental()->filter(fn (array $row) => $row['start_date']->year === $year);

        return $this->summarise($rentalsInYear, fn (array $row) => $row['start_date']->month);
    }

    /** @return Collection<int, array{rental_count: int, revenue: int}> keyed by day of month */
    public function revenuePerDay(int $year, int $month): Collection
    {
        $rentalsInMonth = $this->revenuePerRental()->filter(
            fn (array $row) => $row['start_date']->year === $year && $row['start_date']->month === $month
        );

        return $this->summarise($rentalsInMonth, fn (array $row) => $row['start_date']->day);
    }

    public function revenuePerGarageType(): Collection
    {
        return $this->summarise($this->revenuePerRental(), fn (array $row) => $row['garage_type']->value);
    }

    public function revenuePerPenyewa(): Collection
    {
        return $this->summarise($this->revenuePerRental(), fn (array $row) => $row['penyewa']->name);
    }

    private function chargingRevenueOf(Rental $segment): int
    {
        return $segment->chargingTransactions->sum(
            fn ($chargingTransaction) => $this->chargingService->costOf($chargingTransaction)
        );
    }

    /** One rental = one original row; extensions add revenue, not a new rental. */
    private function summarise(Collection $rows, callable $groupKey): Collection
    {
        return $rows->groupBy($groupKey)->map(fn (Collection $group) => [
            'rental_count' => $group->count(),
            'revenue' => $group->sum('revenue'),
        ]);
    }
}