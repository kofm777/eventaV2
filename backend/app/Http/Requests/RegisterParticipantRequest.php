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
            'gender' => ['required', 'in:Homme,Femme,Autre'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email'],
            'access_type' => ['required', 'in:foire,conference,both'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.required' => 'Le nom est obligatoire.',
            'last_name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'company_name.required' => 'Le nom est obligatoire.',
            'company_name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'gender.required' => 'Le genre est obligatoire.',
            'gender.in' => 'Le genre doit être Homme, Femme ou Autre.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 30 caractères.',
            'phone.regex' => 'Le format du numéro de téléphone n\'est pas valide.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'access_type.required' => 'Le type d\'accès est obligatoire.',
            'access_type.in' => 'Le type d\'accès doit être foire, conference ou both.',
        ];
    }
}
