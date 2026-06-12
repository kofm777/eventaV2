<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the PUBLIC find-my-tickets request (Phase 4).
 *
 * Unauthenticated: the route carries the throttle (3,10 keyed by email+IP) and
 * authorization is always true. Only an email (+ optional order_number) is accepted;
 * the controller ALWAYS returns a generic ok:true so a validation pass never reveals
 * whether the email exists.
 */
class FindTicketRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
