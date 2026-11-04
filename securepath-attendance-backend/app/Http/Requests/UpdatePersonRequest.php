<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends StorePersonRequest
{
    public function rules(): array
    {
        $person = $this->route('person');
        return [
            'firstName' => ['sometimes', 'string', 'max:100'], 'lastName' => ['sometimes', 'string', 'max:100'], 'fullName' => ['sometimes', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', Rule::unique('people', 'email')->ignore($person?->id)], 'phone' => ['nullable', 'string', 'max:40'],
            'departmentId' => ['nullable', 'exists:departments,code'], 'roleId' => ['nullable', 'exists:roles,code'], 'type' => ['sometimes', Rule::in(['employee', 'student', 'staff', 'contractor'])],
            'jobTitle' => ['nullable', 'string', 'max:120'], 'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'biometricConsent' => ['nullable', 'boolean'], 'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
