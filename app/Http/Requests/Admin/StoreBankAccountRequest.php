<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageBank', $this->route('tenant')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_code' => ['required', 'string', 'max:20'],
            'account_holder' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'digits_between:6,20'],
        ];
    }
}
