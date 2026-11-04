<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperAdmin() ?? false; }
    protected function prepareForValidation(): void { if ($this->filled('status')) $this->merge(['status' => strtolower((string) $this->input('status'))]); }
    public function rules(): array
    {
        $id = $this->route('admin')?->id;
        return ['name' => ['sometimes', 'string', 'max:150'], 'email' => ['sometimes', 'email:rfc', Rule::unique('users', 'email')->ignore($id)], 'phone' => ['nullable', 'string', 'max:40'], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'disabled'])]];
    }
}
