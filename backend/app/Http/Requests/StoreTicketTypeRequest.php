<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTypeRequest extends FormRequest
{
    /**
     * Route is already behind auth:sanctum + organizer.active + management roles.
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
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:0'],          // NULL = unlimited
            'max_per_order' => ['nullable', 'integer', 'min:1'],     // NULL = no cap
            'access_tier' => ['required', 'in:fair,fair + conference'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ticket type name is required.',
            'price.min' => 'Price cannot be negative.',
            'currency.size' => 'Currency must be a 3-letter code.',
            'access_tier.in' => 'Access tier must be fair or fair + conference.',
            'sales_end_at.after_or_equal' => 'Sales end must be on or after sales start.',
        ];
    }
}
