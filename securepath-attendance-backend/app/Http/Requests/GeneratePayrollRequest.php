<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'between:1,12']]; }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->integer('year') && $this->integer('month') && Carbon::create($this->integer('year'), $this->integer('month'))->startOfMonth()->isFuture()) {
                $validator->errors()->add('month', 'Future payroll periods cannot be generated.');
            }
        }];
    }
}
