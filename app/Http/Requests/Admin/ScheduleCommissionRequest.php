<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scheduleCommission', $this->route('tenant')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'effective_at' => ['required', 'date', 'after:now'],
        ];
    }
}
