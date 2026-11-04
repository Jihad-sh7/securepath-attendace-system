<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['required', 'string', 'max:150'], 'date' => ['required', 'date'], 'departmentId' => ['nullable', 'exists:departments,code'], 'description' => ['nullable', 'string', 'max:2000']]; }
}

