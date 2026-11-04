<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if ($this->filled('status')) $this->merge(['status' => strtolower((string) $this->input('status'))]); }
    public function rules(): array
    {
        $department = $this->route('department');
        return ['name' => [$department ? 'sometimes' : 'required', 'string', 'max:150'], 'code' => [$department ? 'sometimes' : 'required', 'string', 'max:32', Rule::unique('departments', 'code')->ignore($department?->id)], 'description' => ['nullable', 'string', 'max:2000'], 'managerId' => ['nullable', 'exists:users,id'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'maxOvertimeMinutes' => ['nullable', 'integer', 'min:0', 'max:10080'], 'autoCheckoutEnabled' => ['nullable', 'boolean']];
    }
}
