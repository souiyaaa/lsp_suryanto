<?php

namespace Tests\Unit\Services;

use App\Enums\RentalType;
use App\Models\Garage;
use App\Models\Rental;
use App\Services\ExtensionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ExtensionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.test/proof.jpg';

    private ExtensionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
        $this->service = app(ExtensionService::class);
    }

    private function in(int $days): string
    {
        return today()->addDays($days)->toDateString();
    }

    /** An original rental on its own garage: today .. today + 3. */
    private function activeRental(int $dailyPrice = 40000): Rental
    {
        return Rental::factory()->for(Garage::factory()->create(['daily_price' => $dailyPrice]))->create();
    }

    private function assertRefused(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_active_rentals_today_lists_originals_whose_chain_covers_today(): void
    {
        $active = $this->activeRental();
        $onlyExtensionActive = Rental::factory()->between($this->in(-4), $this->in(0))->create(); // ended today
        $extension = Rental::factory()->extensionOf($onlyExtensionActive, 3)->create();             // covers today
        $ended = Rental::factory()->between($this->in(-9), $this->in(-5))->create();
        $future = Rental::factory()->between($this->in(5), $this->in(8))->create();

        $ids = $this->service->activeRentalsToday()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$active->id, $onlyExtensionActive->id], $ids);
        $this->assertNotContains($extension->id, $ids); // extensions are reached through their original
        $this->assertNotContains($ended->id, $ids);
        $this->assertNotContains($future->id, $ids);
    }

    public function test_current_segment_is_the_one_with_the_latest_end_date(): void
    {
        $original = $this->activeRental();
        $first = Rental::factory()->extensionOf($original, 3)->create();
        $second = Rental::factory()->extensionOf($first, 2)->create();

        $this->assertTrue($this->service->currentSegment($original)->is($second));
        $this->assertTrue($this->service->currentSegment($this->activeRental())->parent_rental_id === null);
    }

    public function test_preview_daily_extension_starts_at_the_current_end_date(): void
    {
        $original = $this->activeRental(40000);

        $preview = $this->service->previewExtension($original, RentalType::Harian, 4);

        $this->assertSame($original->end_date->toDateString(), $preview['start_date']->toDateString());
        $this->assertSame($preview['start_date']->addDays(4)->toDateString(), $preview['end_date']->toDateString());
        $this->assertSame(4, $preview['number_of_days']);
        $this->assertSame(160000, $preview['fee']);
        $this->assertFalse($preview['is_slot_changed']);
        $this->assertTrue($preview['garage']->is($original->garage));
    }

    public function test_preview_monthly_uses_the_segment_start_month(): void
    {
        $original = Rental::factory()->between('2026-11-21', '2026-11-24')->create(); // active only if today is in range
        $this->travelTo('2026-11-22');

        $preview = $this->service->previewExtension($original, RentalType::Bulanan, null);

        // Segment starts 24 Nov: November has 30 days -> ends 24 Dec; fee = 25 x daily price.
        $this->assertSame('2026-12-24', $preview['end_date']->toDateString());
        $this->assertSame(30, $preview['number_of_days']);
        $this->assertSame(25 * $original->garage->daily_price, $preview['fee']);
    }

    public function test_daily_extension_obeys_the_user_limit(): void
    {
        $this->travelTo('2026-11-22');
        $original = Rental::factory()->between('2026-11-21', '2026-11-24')->create(); // segment starts in November: max 29

        $this->assertSame(29, $this->service->previewExtension($original, RentalType::Harian, 29)['number_of_days']);
        $this->assertRefused('number_of_days', fn () => $this->service->previewExtension($original, RentalType::Harian, 30));
        $this->assertRefused('number_of_days', fn () => $this->service->previewExtension($original, RentalType::Harian, 0));
        $this->assertRefused('number_of_days', fn () => $this->service->previewExtension($original, RentalType::Harian, null));
    }

    public function test_only_rentals_active_today_and_originals_can_be_extended(): void
    {
        $ended = Rental::factory()->between($this->in(-9), $this->in(-5))->create();
        $active = $this->activeRental();
        $extension = Rental::factory()->extensionOf($active)->create();

        $this->assertRefused('rental_type', fn () => $this->service->previewExtension($ended, RentalType::Harian, 2));
        $this->expectException(NotFoundHttpException::class);
        $this->service->previewExtension($extension, RentalType::Harian, 2);
    }

    public function test_vehicle_with_another_rental_in_the_range_cannot_be_extended(): void
    {
        $original = $this->activeRental();
        Rental::factory()->for($original->user)->for($original->vehicle, 'vehicle')->between($original->end_date->toDateString(), $this->in(8))->create();

        $this->assertRefused('rental_type', fn () => $this->service->previewExtension($original, RentalType::Harian, 2));
    }

    public function test_slot_conflict_picks_the_cheapest_free_garage_and_uses_its_price(): void
    {
        $original = $this->activeRental(40000);
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        Garage::factory()->create(['daily_price' => 55000]);
        Rental::factory()->for($original->garage)->between($original->end_date->toDateString(), $this->in(8))->create(); // someone else

        $preview = $this->service->previewExtension($original, RentalType::Harian, 2);

        $this->assertTrue($preview['is_slot_changed']);
        $this->assertTrue($preview['garage']->is($cheap));
        $this->assertSame(60000, $preview['fee']); // 2 x the NEW garage's price
    }

    public function test_conflict_is_blocked_when_no_garage_of_the_type_is_free(): void
    {
        $original = $this->activeRental();
        Rental::factory()->for($original->garage)->between($original->end_date->toDateString(), $this->in(8))->create();
        $busy = Garage::factory()->create();
        Rental::factory()->for($busy)->between($this->in(0), $this->in(9))->create();
        Garage::factory()->sepeda()->create(); // another type does not count

        $this->assertRefused('rental_type', fn () => $this->service->previewExtension($original, RentalType::Harian, 2));
    }

    public function test_extend_creates_a_segment_pointing_at_the_original_and_refreshes_the_garage(): void
    {
        $original = $this->activeRental();
        $garageId = $original->garage_id;
        $first = $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $garageId], self::URL);
        $second = $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 3, 'garage_id' => $garageId], self::URL);

        $this->assertSame($original->id, $first->parent_rental_id);
        $this->assertSame($original->id, $second->parent_rental_id); // never the previous extension
        $this->assertSame($original->user_id, $first->user_id);
        $this->assertSame($original->vehicle_id, $first->vehicle_id);
        $this->assertSame(self::URL, $first->payment_proof_url);
        $this->assertSame($first->end_date->toDateString(), $second->start_date->toDateString());
        $this->assertSame(2, $first->numberOfDays());
        $this->assertSame(3, $second->numberOfDays());
        $this->assertTrue(Garage::find($garageId)->is_used);
    }

    public function test_extend_on_a_slot_change_moves_the_segment_to_the_new_garage(): void
    {
        $original = $this->activeRental(40000);
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        Rental::factory()->for($original->garage)->between($original->end_date->toDateString(), $this->in(8))->create();

        $extension = $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $cheap->id], self::URL);

        $this->assertSame($cheap->id, $extension->garage_id);
        $this->assertSame($original->id, $extension->parent_rental_id);
    }

    public function test_extend_refuses_a_garage_that_is_no_longer_the_right_one(): void
    {
        $original = $this->activeRental(40000);
        $cheap = Garage::factory()->create(['daily_price' => 30000]);
        $pricier = Garage::factory()->create(['daily_price' => 70000]);
        Rental::factory()->for($original->garage)->between($original->end_date->toDateString(), $this->in(8))->create();

        // The popup said "cheap", but a more expensive garage (or a missing one) is submitted.
        $this->assertRefused('garage_id', fn () => $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $pricier->id], self::URL));
        $this->assertSame(0, Rental::whereNotNull('parent_rental_id')->count());

        // A garage taken since the preview is refused too.
        Rental::factory()->for($cheap)->between($this->in(3), $this->in(9))->create();
        $this->assertRefused('garage_id', fn () => $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 2, 'garage_id' => $cheap->id], self::URL));
    }

    public function test_extend_accepts_another_equally_cheap_free_garage(): void
    {
        $original = $this->activeRental(40000);
        $twinA = Garage::factory()->create(['daily_price' => 30000]);
        $twinB = Garage::factory()->create(['daily_price' => 30000]);
        Rental::factory()->for($original->garage)->between($original->end_date->toDateString(), $this->in(8))->create();

        foreach ([$twinA, $twinB] as $twin) {
            $extension = $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 1, 'garage_id' => $twin->id], self::URL);
            $this->assertSame($twin->id, $extension->garage_id);
            $extension->delete();
        }
    }

    public function test_extend_rejects_an_unknown_garage_and_a_rule_violation_without_saving(): void
    {
        $original = $this->activeRental();

        $this->assertRefused('number_of_days', fn () => $this->service->extend($original, ['rental_type' => 'harian', 'number_of_days' => 31, 'garage_id' => $original->garage_id], self::URL));
        $this->assertSame(0, Rental::whereNotNull('parent_rental_id')->count());
    }
}
