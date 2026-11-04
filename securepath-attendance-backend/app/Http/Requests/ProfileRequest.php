<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['sometimes', 'string', 'max:150'], 'email' => ['sometimes', 'email:rfc', Rule::unique('users', 'email')->ignore($this->user()?->id)], 'phone' => ['nullable', 'string', 'max:40'], 'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]; }
}

