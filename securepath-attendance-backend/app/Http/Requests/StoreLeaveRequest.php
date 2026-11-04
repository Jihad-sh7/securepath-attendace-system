<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['personId' => ['required', 'exists:people,person_code'], 'startDate' => ['required', 'date'], 'endDate' => ['required', 'date', 'after_or_equal:startDate'], 'type' => ['required', 'string', 'max:64'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]; }
}

