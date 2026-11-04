<?php

namespace App\Http\Requests;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminPermissionsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperAdmin() ?? false; }
    public function rules(): array { return ['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::all())]]; }
}

