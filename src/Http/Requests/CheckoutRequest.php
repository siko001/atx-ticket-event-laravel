<?php

namespace AtxDigital\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxQuantity = (int) config('ticketing.checkout.max_quantity_per_type', 10);

        return [
            'occurrence_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', "max:{$maxQuantity}"],
            'purchaser' => ['required', 'array'],
            'purchaser.name' => ['required', 'string', 'max:255'],
            'purchaser.email' => ['required', 'email', 'max:255'],
            'purchaser.phone' => ['nullable', 'string', 'max:64'],
            'purchaser.organisation' => ['nullable', 'string', 'max:255'],
            'purchaser.country' => ['nullable', 'string', 'max:64'],
            'attendees' => ['nullable', 'array'],
            'attendees.*.ticket_type_id' => ['required', 'integer'],
            'attendees.*.name' => ['required', 'string', 'max:255'],
            'attendees.*.email' => ['required', 'email', 'max:255'],
            'attendees.*.phone' => ['nullable', 'string', 'max:64'],
            'attendees.*.organisation' => ['nullable', 'string', 'max:255'],
            'attendees.*.country' => ['nullable', 'string', 'max:64'],
            'attendees.*.answers' => ['nullable', 'array'],
            'answers' => ['nullable', 'array'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'success_url' => ['nullable', 'url', 'max:2000'],
            'cancel_url' => ['nullable', 'url', 'max:2000'],
        ];
    }
}
