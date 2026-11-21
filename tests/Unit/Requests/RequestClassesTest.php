<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Admin\GarageBatchRequest;
use App\Http\Requests\ChargingRequest;
use App\Http\Requests\RentalRequest;
use App\Http\Requests\VehicleRequest;
use App\Services\Generators\GarageCodeGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Guards against broken imports: every request must build its rules without errors. */
class RequestClassesTest extends TestCase
{
    public function test_shared_payment_proof_rule_is_used_by_rental_and_charging_requests(): void
    {
        foreach ([ChargingRequest::class, RentalRequest::class] as $class) {
            $request = new $class;
            $request->setUserResolver(fn () => new \App\Models\User(['id' => 1]));
            $rules = $request->rules()['payment_proof'];

            $this->assertContains('required', $rules);
            $this->assertContains('mimes:jpg,jpeg,png,webp', $rules);
            $this->assertContains('max:2048', $rules);
        }

        $pdf = UploadedFile::fake()->create('x.pdf', 10, 'application/pdf');
        $this->assertTrue(Validator::make(['payment_proof' => $pdf], ['payment_proof' => (new ChargingRequest)->rules()['payment_proof']])->fails());
    }

    public function test_garage_batch_request_uses_generator_limit(): void
    {
        $rules = (new GarageBatchRequest)->rules();

        $this->assertContains('max:'.GarageCodeGenerator::MAX_GARAGES_PER_TYPE, $rules['amount']);
    }

    public function test_vehicle_request_builds_rules_for_battery_types(): void
    {
        $request = VehicleRequest::create('/', 'POST', ['type' => 'mobil_listrik']);
        $rules = $request->rules();

        $this->assertArrayHasKey('max_battery_kwh', $rules);
        $this->assertInstanceOf(\Illuminate\Validation\Rules\RequiredIf::class, $rules['max_battery_kwh'][0]);
        $this->assertTrue($rules['max_battery_kwh'][0]->condition);
    }
}
