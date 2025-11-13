<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterParticipantRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email'],
            'access_type' => ['required', 'in:fair,fair + conference'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name cannot exceed 255 characters.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name cannot exceed 255 characters.',
            'company_name.required' => 'Company name is required.',
            'company_name.max' => 'Company name cannot exceed 255 characters.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male, Female, or Other.',
            'phone.max' => 'Phone number cannot exceed 30 characters.',
            'phone.regex' => 'Phone number format is invalid.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Email address is invalid.',
            'email.unique' => 'This email address is already in use.',
            'access_type.required' => 'Access type is required.',
            'access_type.in' => 'Access type must be fair or fair + conference.',
        ];
    }
}
