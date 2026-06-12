<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketTypeRequest extends FormRequest
{
    /**
     * Route is already behind auth:sanctum + organizer.active + management roles.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same rules as Store but every field is `sometimes` (partial update).
     * quantity-not-below-quantity_sold is enforced in the controller against the row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_per_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'access_tier' => ['sometimes', 'required', 'in:fair,fair + conference'],
            'sales_start_at' => ['sometimes', 'nullable', 'date'],
            'sales_end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:sales_start_at'],
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
            'price.min' => 'Price cannot be negative.',
            'currency.size' => 'Currency must be a 3-letter code.',
            'access_tier.in' => 'Access tier must be fair or fair + conference.',
            'sales_end_at.after_or_equal' => 'Sales end must be on or after sales start.',
        ];
    }
}
