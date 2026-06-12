<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin + attendee authentication happy/sad paths.
 *
 * Matched to AuthController + AttendeeAuthController:
 *  - admin login success -> 200 {ok:true, token, ...} (Hash::check against the model).
 *  - admin login wrong password -> 422 (ValidationException renders 422 under
 *    postJson's Accept: application/json).
 *  - attendee register -> 201 {ok:true, attendee.email_verified:false, token}.
 *  - attendee login -> 200 {ok:true, token}.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_with_correct_credentials_returns_token(): void
    {
        // password is auto-hashed by the Admin 'password' => 'hashed' cast.
        Admin::create([
            'name' => 'Platform Admin',
            'email' => 'admin.login@example.com',
            'password' => 'secret-password',
            'organizer_id' => null,
            'role' => Admin::ROLE_SUPERADMIN,
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.login@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonStructure([
                'ok',
                'admin' => ['id', 'name', 'email', 'role', 'organizer_id'],
                'token',
                'message',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_admin_login_with_wrong_password_is_rejected(): void
    {
        Admin::create([
            'name' => 'Platform Admin',
            'email' => 'admin.wrong@example.com',
            'password' => 'secret-password',
            'organizer_id' => null,
            'role' => Admin::ROLE_SUPERADMIN,
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin.wrong@example.com',
            'password' => 'not-the-password',
        ]);

        // AuthController throws ValidationException -> 422 with an 'email' error key.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_attendee_can_register(): void
    {
        $response = $this->postJson('/api/v1/attendee/register', [
            'name' => 'Sam Buyer',
            'email' => 'sam.buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'attendee' => [
                    'email' => 'sam.buyer@example.com',
                    'email_verified' => false,
                ],
            ])
            ->assertJsonStructure([
                'ok',
                'attendee' => ['id', 'name', 'email', 'phone', 'email_verified'],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('attendees', [
            'email' => 'sam.buyer@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_attendee_can_login(): void
    {
        // Register first so the credentials exist (password auto-hashed by the cast).
        $this->postJson('/api/v1/attendee/register', [
            'name' => 'Lee Login',
            'email' => 'lee.login@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/attendee/login', [
            'email' => 'lee.login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true])
            ->assertJsonStructure([
                'ok',
                'attendee' => ['id', 'name', 'email', 'email_verified'],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }
}
