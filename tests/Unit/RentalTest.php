<?php

namespace Tests\Unit;

use App\Enums\RentalType;
use App\Models\ChargingTransaction;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    private function spec(): Rental
    {
        return Rental::factory()
            ->for(Garage::factory()->create(['code' => 'C001']))
            ->between('2026-11-21', '2026-11-24')
            ->create();
    }

    public function test_casts_and_number_of_days(): void
    {
        $rental = $this->spec()->fresh();

        $this->assertSame(RentalType::Harian, $rental->rental_type);
        $this->assertInstanceOf(CarbonImmutable::class, $rental->start_date);
        $this->assertInstanceOf(CarbonImmutable::class, $rental->end_date);
        $this->assertSame(3, $rental->numberOfDays());
    }

    public function test_dates_are_stored_without_time_part(): void
    {
        $rental = $this->spec();
        $raw = DB::table('rentals')->find($rental->id);

        $this->assertSame('2026-11-21', $raw->start_date);
        $this->assertSame('2026-11-24', $raw->end_date);
    }

    public function test_belongs_to_relations(): void
    {
        $rental = $this->spec();

        $this->assertInstanceOf(User::class, $rental->user);
        $this->assertInstanceOf(Vehicle::class, $rental->vehicle);
        $this->assertSame('C001', $rental->garage->code);
        $this->assertTrue($rental->vehicle->user->is($rental->user));
    }

    public function test_carbon_dates_are_stored_as_plain_dates(): void
    {
        // The services save CarbonImmutable values; a time part would break the string
        // comparisons in coveringDate()/overlapping() on SQLite.
        $rental = Rental::factory()->create([
            'start_date' => CarbonImmutable::parse('2026-11-21'),
            'end_date' => CarbonImmutable::parse('2026-11-24'),
        ]);

        $raw = DB::table('rentals')->where('id', $rental->id)->first();

        $this->assertSame('2026-11-21', $raw->start_date);
        $this->assertSame('2026-11-24', $raw->end_date);
        $this->assertTrue(Rental::coveringDate(CarbonImmutable::parse('2026-11-21'))->whereKey($rental->id)->exists());
    }

    public function test_covering_date_excludes_end_date(): void
    {
        $rental = $this->spec();

        $this->assertTrue(Rental::coveringDate(CarbonImmutable::parse('2026-11-21'))->whereKey($rental->id)->exists());
        $this->assertTrue(Rental::coveringDate(CarbonImmutable::parse('2026-11-23'))->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::coveringDate(CarbonImmutable::parse('2026-11-24'))->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::coveringDate(CarbonImmutable::parse('2026-11-20'))->whereKey($rental->id)->exists());
    }

    public function test_scopes_accept_mutable_carbon_from_today(): void
    {
        $rental = Rental::factory()->create(); // today .. today + 3

        $this->assertInstanceOf(\Carbon\Carbon::class, today());
        $this->assertTrue(Rental::coveringDate(today())->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::coveringDate(today()->addDays(3))->whereKey($rental->id)->exists());
        $this->assertTrue(Rental::overlapping(today()->addDays(2), today()->addDays(5))->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::overlapping(today()->addDays(3), today()->addDays(5))->whereKey($rental->id)->exists());
    }

    public function test_scopes_return_the_builder_so_they_chain(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Builder::class,
            Rental::coveringDate(today())->overlapping(today(), today()->addDay())
        );
    }

    public function test_overlapping_scope(): void
    {
        $rental = $this->spec();

        $this->assertTrue(Rental::overlapping(CarbonImmutable::parse('2026-11-23'), CarbonImmutable::parse('2026-11-25'))->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::overlapping(CarbonImmutable::parse('2026-11-24'), CarbonImmutable::parse('2026-11-26'))->whereKey($rental->id)->exists());
        $this->assertFalse(Rental::overlapping(CarbonImmutable::parse('2026-11-18'), CarbonImmutable::parse('2026-11-21'))->whereKey($rental->id)->exists());
        $this->assertTrue(Rental::overlapping(CarbonImmutable::parse('2026-11-22'), CarbonImmutable::parse('2026-11-23'))->whereKey($rental->id)->exists());
    }

    public function test_original_has_null_parent_and_extensions_point_to_original(): void
    {
        $original = $this->spec();
        $first = Rental::factory()->extensionOf($original)->create();
        $second = Rental::factory()->extensionOf($first)->create(); // built from an extension

        $this->assertNull($original->parent_rental_id);
        $this->assertFalse($original->isExtension());
        $this->assertTrue($first->isExtension());
        $this->assertSame($original->id, $first->parent_rental_id);
        $this->assertSame($original->id, $second->parent_rental_id); // never the previous extension
        $this->assertCount(2, $original->extensions);
        $this->assertTrue($second->parentRental->is($original));
    }

    public function test_in_chain_of_returns_original_and_all_its_extensions(): void
    {
        $original = $this->spec(); // 21-24 Nov
        $first = Rental::factory()->extensionOf($original)->create(); // 24-27
        $second = Rental::factory()->extensionOf($first)->create(); // 27-30

        $ids = Rental::inChainOf($original)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$original->id, $first->id, $second->id], $ids);
    }

    public function test_in_chain_of_excludes_other_chains_and_unrelated_rentals(): void
    {
        $original = $this->spec();
        $ext = Rental::factory()->extensionOf($original)->create();

        $otherOriginal = Rental::factory()->between('2026-11-21', '2026-11-24')->create();
        $otherExt = Rental::factory()->extensionOf($otherOriginal)->create();
        $standalone = Rental::factory()->create();

        $ids = Rental::inChainOf($original)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$original->id, $ext->id], $ids);
        $this->assertNotContains($otherOriginal->id, $ids);
        $this->assertNotContains($otherExt->id, $ids);
        $this->assertNotContains($standalone->id, $ids);
    }

    public function test_in_chain_of_includes_segments_on_other_garages(): void
    {
        $original = $this->spec();
        $other = Garage::factory()->create(['code' => 'C002']);
        $moved = Rental::factory()->extensionOf($original)->create(['garage_id' => $other->id]);

        $this->assertEqualsCanonicalizing(
            [$original->id, $moved->id],
            Rental::inChainOf($original)->pluck('id')->all()
        );
    }

    public function test_in_chain_of_is_grouped_so_it_does_not_leak_when_chained(): void
    {
        $original = $this->spec(); // 21-24
        $ext = Rental::factory()->extensionOf($original)->create(); // 24-27
        $unrelated = Rental::factory()->between('2026-11-25', '2026-11-26')->create();

        // Without the closure the orWhere would let $unrelated (or other rows) through.
        $ids = Rental::inChainOf($original)->coveringDate(CarbonImmutable::parse('2026-11-25'))->pluck('id')->all();

        $this->assertSame([$ext->id], $ids);
        $this->assertNotContains($unrelated->id, $ids);
        $this->assertStringContainsString(
            '("rentals"."id" = ? or "parent_rental_id" = ?)',
            Rental::inChainOf($original)->toSql()
        );
    }

    public function test_in_chain_of_current_segment_lookup_for_a_date(): void
    {
        $original = $this->spec(); // 21-24
        $ext = Rental::factory()->extensionOf($original)->create(); // 24-27

        $this->assertTrue(Rental::inChainOf($original)->coveringDate(CarbonImmutable::parse('2026-11-23'))->first()->is($original));
        $this->assertTrue(Rental::inChainOf($original)->coveringDate(CarbonImmutable::parse('2026-11-24'))->first()->is($ext));
        $this->assertNull(Rental::inChainOf($original)->coveringDate(CarbonImmutable::parse('2026-11-27'))->first());
    }

    public function test_extension_on_another_garage_stays_in_same_chain(): void
    {
        $original = $this->spec();
        $other = Garage::factory()->create(['code' => 'C002']);
        $ext = Rental::factory()->extensionOf($original)->create(['garage_id' => $other->id]);

        $this->assertSame($original->id, $ext->parent_rental_id);
        $this->assertSame('C002', $ext->garage->code);
        $this->assertNotSame($original->garage_id, $ext->garage_id);
    }

    public function test_charging_transactions_relation(): void
    {
        $rental = $this->spec();
        ChargingTransaction::factory()->count(2)->for($rental)->create();

        $this->assertCount(2, $rental->chargingTransactions);
    }

    public function test_deleting_referenced_rows_is_restricted(): void
    {
        $original = $this->spec();
        Rental::factory()->extensionOf($original)->create();

        foreach ([$original, $original->garage, $original->vehicle, $original->user] as $model) {
            try {
                $model->delete();
                $this->fail('Expected FK restrict for '.class_basename($model));
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_model_helpers_on_new_vehicle_and_garage_relations(): void
    {
        $rental = $this->spec();

        $this->assertTrue($rental->user->rentals->contains($rental));
        $this->assertTrue($rental->vehicle->rentals->contains($rental));
        $this->assertTrue($rental->garage->rentals->contains($rental));
    }
}
