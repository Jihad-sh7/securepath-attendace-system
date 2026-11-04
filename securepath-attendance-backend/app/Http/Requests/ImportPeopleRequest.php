<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportPeopleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], 'updateExisting' => ['nullable', 'boolean']]; }
}

