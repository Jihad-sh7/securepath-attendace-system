<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceLoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['identifier' => ['required', 'string', 'max:100'], 'secret' => ['required', 'string', 'max:255']]; }
}

