<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Participant;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_register_successfully()
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'gender' => 'Male',
            'phone' => '+33 6 12 34 56 78',
            'email' => 'jean.dupont@example.com',
            'access_type' => 'both',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
            ])
            ->assertJsonStructure([
                'ok',
                'participant' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'status',
                ],
                'qr',
                'message',
            ]);

        $this->assertDatabaseHas('participants', [
            'email' => 'jean.dupont@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_registration_requires_required_fields()
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'gender', 'email', 'access_type']);
    }

    public function test_registration_validates_email_format()
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'gender' => 'Male',
            'email' => 'invalid-email',
            'access_type' => 'foire',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_prevents_duplicate_email()
    {
        Participant::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'gender' => 'Male',
            'email' => 'existing@example.com',
            'access_type' => 'foire',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_validates_gender_enum()
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'gender' => 'Invalid',
            'email' => 'jean@example.com',
            'access_type' => 'foire',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_registration_validates_access_type_enum()
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'gender' => 'Male',
            'email' => 'jean@example.com',
            'access_type' => 'invalid',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['access_type']);
    }
}
