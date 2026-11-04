<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Exceptions\ApiConflictException;
use App\Models\Role;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class RoleController extends Controller
{
    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated();
        $paginator = Role::withCount('people')->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))->orderBy('name')->paginate($filters['limit'] ?? 100);
        return ApiResponse::paginated($paginator, RoleResource::class);
    }

    public function store(StoreRoleRequest $request, AuditService $audit)
    {
        $role = Role::create($request->validated());
        $audit->record('role.created', $role, newValues: $role->toArray());
        return ApiResponse::success((new RoleResource($role->loadCount('people')))->resolve(), 'Role created successfully.', 201);
    }

    public function update(StoreRoleRequest $request, Role $role, AuditService $audit)
    {
        $old = $role->toArray(); $role->update($request->validated()); $audit->record('role.updated', $role, $old, $role->fresh()->toArray());
        return ApiResponse::success((new RoleResource($role->fresh()->loadCount('people')))->resolve());
    }

    public function destroy(Role $role, AuditService $audit)
    {
        if ($role->people()->exists() || $role->rates()->exists()) throw new ApiConflictException('Roles assigned to people or pay rates cannot be deleted.', 'ROLE_IN_USE');
        $audit->record('role.deleted', $role, $role->toArray()); $role->delete(); return response()->noContent();
    }
}
