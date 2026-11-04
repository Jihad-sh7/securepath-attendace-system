<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if ($this->filled('status')) $this->merge(['status' => strtolower(str_replace(' ', '_', (string) $this->input('status')))]); }
    public function rules(): array { return ['status' => ['required', 'string', Rule::in(['active', 'inactive', 'suspended', 'disabled', 'open', 'investigating', 'resolved', 'dismissed'])], 'note' => ['nullable', 'string', 'max:2000'], 'assignedTo' => ['nullable', 'exists:users,id']]; }
}
