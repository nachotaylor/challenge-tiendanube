<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => ['required', 'string', 'max:255'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'cardNumber' => ['required', 'string', 'regex:/^\d{4,19}$/'],
            'cardHolderName' => ['required', 'string', 'max:255'],
            'cardExpirationDate' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cardCvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.regex' => 'The value must be a decimal string with up to two decimal places.',
            'cardExpirationDate.regex' => 'The card expiration date must use the MM/YY format.',
        ];
    }
}
