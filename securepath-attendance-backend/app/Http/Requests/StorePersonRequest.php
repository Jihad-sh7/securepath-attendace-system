<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['type', 'status'] as $field) if ($this->filled($field)) $values[$field] = strtolower((string) $this->input($field));
        $this->merge($values);
    }
    public function rules(): array
    {
        return [
            'personCode' => ['nullable', 'string', 'max:32', 'unique:people,person_code'],
            'firstName' => ['required_without:fullName', 'string', 'max:100'],
            'lastName' => ['required_without:fullName', 'string', 'max:100'],
            'fullName' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'unique:people,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'departmentId' => ['nullable', 'exists:departments,code'],
            'roleId' => ['nullable', 'exists:roles,code'],
            'type' => ['nullable', Rule::in(['employee', 'student', 'staff', 'contractor'])],
            'jobTitle' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'biometricConsent' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
