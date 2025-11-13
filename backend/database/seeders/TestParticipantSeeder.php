<?php

namespace Database\Seeders;

use App\Models\Participant;
use Illuminate\Database\Seeder;

class TestParticipantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Participant::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'Male',
            'phone' => '+33123456789',
            'email' => 'john.doe@example.com',
            'access_type' => 'both',
            'status' => 'accepted',
            'qr_token' => 'test_token',
            'qr_payload' => ['test' => 'data'],
        ]);

        Participant::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'gender' => 'Female',
            'phone' => '+33987654321',
            'email' => 'jane.smith@example.com',
            'access_type' => 'foire',
            'status' => 'pending',
            'qr_token' => 'test_token2',
            'qr_payload' => ['test' => 'data2'],
        ]);
    }
}
