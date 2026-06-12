<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 6 — validates public attendee account signup.
 *
 * Mirrors OrganizerSignupRequest: public endpoint (route carries the throttle),
 * authorize() is always true. Email is unique against the `attendees` table (a
 * SEPARATE namespace from admins — the same address may exist in both). The phone
 * regex matches the buyer phone rule used by PurchaseTicketRequest.
 */
class AttendeeRegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:attendees,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Email address is invalid.',
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'phone.regex' => 'Phone number format is invalid.',
        ];
    }
}
