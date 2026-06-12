<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotent: safe to run on every deploy (migrate --seed runs at container start).
        // Env-driven: never seed a default weak password.
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (empty($password)) {
            Log::warning('AdminSeeder skipped: ADMIN_PASSWORD is not set; no admin account seeded.');

            return;
        }

        // updateOrCreate keyed on email: rotates the password to the current env value.
        // The seeded env admin is the platform super-admin: organizer_id NULL +
        // role 'superadmin' so it bypasses the tenant scope and sees every organizer.
        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
                'organizer_id' => null,
                'role' => Admin::ROLE_SUPERADMIN,
            ]
        );
    }
}
