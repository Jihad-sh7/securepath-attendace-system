<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\ApiResponse;
use App\Support\PermissionCatalog;

class PermissionController extends Controller
{
    public function __invoke() { return ApiResponse::success(['groups' => PermissionCatalog::grouped(), 'permissions' => Permission::orderBy('group')->orderBy('name')->get(['name', 'group', 'description'])]); }
}

