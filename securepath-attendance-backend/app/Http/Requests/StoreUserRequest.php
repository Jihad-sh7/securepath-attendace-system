<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if ($this->filled('status')) $this->merge(['status' => strtolower((string) $this->input('status'))]); }
    public function rules(): array
    {
        $user = $this->route('user');
        return ['name' => [$user ? 'sometimes' : 'required', 'string', 'max:150'], 'email' => [$user ? 'sometimes' : 'required', 'email:rfc', Rule::unique('users', 'email')->ignore($user?->id)], 'phone' => ['nullable', 'string', 'max:40'], 'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()], 'title' => ['nullable', 'string', 'max:100'], 'personId' => ['nullable', 'exists:people,person_code'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])], 'twoFactorEnabled' => ['nullable', 'boolean']];
    }
}
