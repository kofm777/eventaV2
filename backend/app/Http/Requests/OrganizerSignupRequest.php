<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the PUBLIC organizer self-signup request.
 *
 * Mirrors the existing public RegistrationController flow: unauthenticated, so the
 * route (not this request) carries the throttle, and authorization is always true.
 * Creates an Organizer (status=pending) + its owner Admin.
 */
class OrganizerSignupRequest extends FormRequest
{
    /**
     * Public endpoint — no auth required.
     */
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
            'organizer_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organizer_name.required' => 'Organizer name is required.',
            'admin_name.required' => 'Your name is required.',
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
