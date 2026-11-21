<?php

namespace Tests\Unit\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Services\GarageBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GarageBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_garages_with_sequential_codes_and_the_batch_price(): void
    {
        $created = app(GarageBatchService::class)->createBatch(GarageType::Mobil, 3, 40000, null);

        $this->assertSame(['C001', 'C002', 'C003'], $created->pluck('code')->all());
        $this->assertSame([40000], $created->pluck('daily_price')->unique()->values()->all());
        $this->assertFalse($created->first()->fresh()->is_used);
        $this->assertSame(3, Garage::count());
    }

    public function test_a_second_batch_continues_numbering_and_may_have_another_price(): void
    {
        $service = app(GarageBatchService::class);
        $service->createBatch(GarageType::Mobil, 2, 40000, null);

        $second = $service->createBatch(GarageType::Mobil, 2, 55000, null);

        $this->assertSame(['C003', 'C004'], $second->pluck('code')->all());
        $this->assertSame(40000, Garage::where('code', 'C001')->value('daily_price'));
        $this->assertSame(55000, Garage::where('code', 'C003')->value('daily_price'));
    }

    public function test_numbering_is_independent_per_type(): void
    {
        $service = app(GarageBatchService::class);
        $service->createBatch(GarageType::Mobil, 2, 40000, null);

        $this->assertSame(['S001'], $service->createBatch(GarageType::Sepeda, 1, 10000, null)->pluck('code')->all());
        $this->assertSame(['E001'], $service->createBatch(GarageType::MobilListrik, 1, 50000, 2500)->pluck('code')->all());
    }

    public function test_charging_price_is_stored_only_for_electric_garages(): void
    {
        $service = app(GarageBatchService::class);

        $electric = $service->createBatch(GarageType::MobilListrik, 1, 50000, 2500)->first();
        $petrol = $service->createBatch(GarageType::Mobil, 1, 40000, 2500)->first();

        $this->assertSame(2500, $electric->charging_price_per_kwh);
        $this->assertNull($petrol->charging_price_per_kwh);
    }

    public function test_amount_above_the_remaining_capacity_is_rejected_and_nothing_is_created(): void
    {
        Garage::factory()->count(3)->sequence(
            ['code' => 'C001'], ['code' => 'C002'], ['code' => 'C003']
        )->create();

        try {
            app(GarageBatchService::class)->createBatch(GarageType::Mobil, 997, 40000, null);
            $this->fail('Expected a capacity error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('996', $e->errors()['amount'][0]);
        }

        $this->assertSame(3, Garage::count());
        $this->assertSame(996, app(GarageBatchService::class)->createBatch(GarageType::Mobil, 996, 40000, null)->count());
        $this->assertSame('C999', Garage::where('type', 'mobil')->orderByDesc('code')->value('code'));
    }
}
