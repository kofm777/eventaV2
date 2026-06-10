<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mirrors RegisterParticipantRequest, EXCEPT the email is NOT unique-constrained
     * here because guests may re-buy / buy for events they previously attended.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'access_type' => ['required', 'in:fair,fair + conference'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male, Female, or Other.',
            'phone.regex' => 'Phone number format is invalid.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Email address is invalid.',
            'access_type.required' => 'Access type is required.',
            'access_type.in' => 'Access type must be fair or fair + conference.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
