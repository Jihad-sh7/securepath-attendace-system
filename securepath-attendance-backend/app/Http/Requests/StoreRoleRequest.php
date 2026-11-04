<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        if ($this->filled('status')) $this->merge(['status' => strtolower((string) $this->input('status'))]);
    }
    public function rules(): array
    {
        $role = $this->route('role');
        return ['name' => [$role ? 'sometimes' : 'required', 'string', 'max:150'], 'code' => [$role ? 'sometimes' : 'required', 'alpha_dash', 'max:64', Rule::unique('roles', 'code')->ignore($role?->id)], 'description' => ['nullable', 'string', 'max:2000'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]];
    }
}
