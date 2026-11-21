<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\Event;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshGarageStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-21'));
    }

    public function test_command_recomputes_is_used_for_today(): void
    {
        $starting = Garage::factory()->create(['is_used' => false]);
        Rental::factory()->for($starting)->create(); // an advance booking that starts today
        $ended = Garage::factory()->create(['is_used' => true]);
        Rental::factory()->for($ended)->between(today()->subDays(3)->toDateString(), today()->toDateString())->create();

        $this->artisan('garages:refresh-status')
            ->expectsOutput('Garage status refreshed for '.today()->toDateString())
            ->assertExitCode(0);

        $this->assertTrue($starting->fresh()->is_used);
        $this->assertFalse($ended->fresh()->is_used);
    }

    public function test_command_is_scheduled_daily_at_midnight_jakarta(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command, 'garages:refresh-status'));

        $this->assertNotNull($event, 'garages:refresh-status is not scheduled');
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', (string) $event->timezone);
    }
}
