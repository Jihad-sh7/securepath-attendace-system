<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
    }
    public function rules(): array
    {
        return ['departmentId' => ['required', 'exists:departments,code'], 'roleId' => ['required', 'exists:roles,code'], 'hourlyRate' => ['required', 'decimal:0,4', 'gt:0', 'max:99999999'], 'currency' => ['nullable', 'string', 'size:3'], 'effectiveFrom' => ['required', 'date'], 'effectiveUntil' => ['nullable', 'date', 'after_or_equal:effectiveFrom']];
    }
}
