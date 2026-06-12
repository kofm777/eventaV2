<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 6 — validates attendee sign-in (email + password).
 *
 * Public endpoint (route carries the throttle); authorize() is always true. Credential
 * verification + the generic invalid-credentials error live in AttendeeAuthController
 * (no email-existence leak), mirroring AuthController.
 */
class AttendeeLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
