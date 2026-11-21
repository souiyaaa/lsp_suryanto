<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Safe to run more than once.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedDemoUsers();
        $this->seedDemoGarages();
    }

    /** The single admin account (FR-05). role/status are not fillable, so forceFill. */
    private function seedAdmin(): void
    {
        $password = config('app.admin_password');

        if (blank($password)) {
            throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding.');
        }

        $admin = User::firstOrNew(['email' => 'admin@garasi.test']);
        $admin->forceFill([
            'name' => 'Admin Garasi',
            'nik' => '0000000000000001',
            'phone' => '080000000001',
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
            'password' => $password, // hashed by the model cast
            'ktp_verified_at' => $admin->ktp_verified_at ?? now(),
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }

    /** Demo: one approved penyewa with one vehicle per type, and one pending penyewa. */
    private function seedDemoUsers(): void
    {
        $approved = User::firstOrNew(['email' => 'penyewa@garasi.test']);
        $approved->forceFill([
            'name' => 'Budi Penyewa',
            'nik' => '3171000000000002',
            'phone' => '081200000002',
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Approved,
            'password' => 'password',
            'ktp_verified_at' => $approved->ktp_verified_at ?? now(),
            'email_verified_at' => $approved->email_verified_at ?? now(),
        ])->save();

        $vehicles = [
            ['type' => 'sepeda', 'plate_number' => null, 'brand_model' => 'Polygon Xtrada', 'max_battery_kwh' => null],
            ['type' => 'sepeda_motor', 'plate_number' => 'B 1111 SM', 'brand_model' => 'Honda Vario 160', 'max_battery_kwh' => null],
            ['type' => 'mobil', 'plate_number' => 'B 2222 MB', 'brand_model' => 'Toyota Avanza', 'max_battery_kwh' => null],
            ['type' => 'mobil_listrik', 'plate_number' => 'B 3333 ML', 'brand_model' => 'Hyundai Ioniq 5', 'max_battery_kwh' => 60.00],
        ];
        foreach ($vehicles as $vehicle) {
            $approved->vehicles()->updateOrCreate(
                ['type' => $vehicle['type'], 'brand_model' => $vehicle['brand_model']],
                $vehicle,
            );
        }

        $pending = User::firstOrNew(['email' => 'pending@garasi.test']);
        $pending->forceFill([
            'name' => 'Siti Pending',
            'nik' => '3171000000000003',
            'phone' => '081200000003',
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Pending,
            'password' => 'password',
        ])->save();
    }

    /** Demo: a few garages per type; two E/C batches show different prices per type. */
    private function seedDemoGarages(): void
    {
        $garages = [
            ['S001', 'sepeda', 10000, null],
            ['S002', 'sepeda', 10000, null],
            ['S003', 'sepeda', 12000, null],
            ['C001', 'mobil', 40000, null],
            ['C002', 'mobil', 40000, null],
            ['C003', 'mobil', 55000, null],
            ['E001', 'mobil_listrik', 50000, 2500],
            ['E002', 'mobil_listrik', 50000, 2500],
            ['E003', 'mobil_listrik', 65000, 3000],
        ];

        foreach ($garages as [$code, $type, $daily, $charging]) {
            // is_used is left alone: GarageStatusService::refresh() owns it (default false).
            Garage::updateOrCreate(['code' => $code], [
                'type' => $type,
                'daily_price' => $daily,
                'charging_price_per_kwh' => $charging,
            ]);
        }
    }
}
