<?php

namespace Tests\Feature;

use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public free-registration endpoint (POST /api/v1/register).
 *
 * Aligned to the REAL app:
 *  - RegisterParticipantRequest requires company_name and access_type in
 *    'fair','fair + conference' (NOT the old 'both'/'foire' strings).
 *  - RegistrationController::register always stores status 'accepted' (not 'pending')
 *    and returns 200 with {ok, participant, qr, qr_url, email_sent, message}.
 *  - The Demo Organizer (slug 'demo-organizer') is created by the
 *    2026_06_12_000100 data migration, so resolveOrganizerId() finds a tenant under
 *    RefreshDatabase with no extra seeding.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_register_successfully(): void
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme Corp',
            'gender' => 'Male',
            'phone' => '+33 6 12 34 56 78',
            'email' => 'jean.dupont@example.com',
            'access_type' => 'fair',
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
                    'company_name',
                    'email',
                    'access_type',
                    'status',
                ],
                'qr',
                'qr_url',
                'email_sent',
                'message',
            ]);

        // The controller hard-codes status 'accepted' (always accepted).
        $this->assertDatabaseHas('participants', [
            'email' => 'jean.dupont@example.com',
            'access_type' => 'fair',
            'status' => 'accepted',
        ]);
    }

    public function test_registration_requires_required_fields(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'company_name',
                'gender',
                'email',
                'access_type',
            ]);
    }

    public function test_registration_validates_email_format(): void
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme Corp',
            'gender' => 'Male',
            'email' => 'invalid-email',
            'access_type' => 'fair',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_prevents_duplicate_email(): void
    {
        // Override factory access_type/status to values valid for the participants
        // enum columns ('fair','fair + conference' / 'pending','accepted','rejected')
        // since the factory's random pool includes legacy strings not in the enum.
        Participant::factory()->create([
            'email' => 'existing@example.com',
            'access_type' => 'fair',
            'status' => 'accepted',
        ]);

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme Corp',
            'gender' => 'Male',
            'email' => 'existing@example.com',
            'access_type' => 'fair',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_validates_gender_enum(): void
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme Corp',
            'gender' => 'Invalid',
            'email' => 'jean@example.com',
            'access_type' => 'fair',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_registration_validates_access_type_enum(): void
    {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'Acme Corp',
            'gender' => 'Male',
            'email' => 'jean@example.com',
            'access_type' => 'invalid',
        ];

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['access_type']);
    }
}
