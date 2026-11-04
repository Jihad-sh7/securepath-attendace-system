<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminPermissionsRequest;
use App\Http\Requests\UpdateAdminRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class AdminController extends Controller
{
    public function __construct(private readonly AdminService $admins) {}

    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated();
        $paginator = User::with('permissions')->whereIn('role', [UserRole::SuperAdmin, UserRole::Admin])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', strtolower($status)))
            ->orderBy('role')->orderBy('name')->paginate($filters['limit'] ?? 20);
        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function show(User $admin) { $this->ensureAdmin($admin); return ApiResponse::success((new UserResource($admin->load('permissions')))->resolve()); }
    public function store(StoreAdminRequest $request) { return ApiResponse::success((new UserResource($this->admins->create($request->validated())))->resolve(), 'Admin created successfully.', 201); }
    public function update(UpdateAdminRequest $request, User $admin) { return ApiResponse::success((new UserResource($this->admins->update($admin, $request->validated())))->resolve(), 'Admin updated successfully.'); }
    public function destroy(User $admin) { $this->admins->delete($admin); return response()->noContent(); }
    public function status(StatusRequest $request, User $admin) { return ApiResponse::success((new UserResource($this->admins->updateStatus($admin, $request->string('status'))))->resolve(), 'Admin status updated.'); }
    public function permissions(User $admin) { $this->ensureAdmin($admin); return ApiResponse::success(['permissions' => $admin->isSuperAdmin() ? [] : $admin->permissions()->pluck('name')->values()]); }
    public function updatePermissions(UpdateAdminPermissionsRequest $request, User $admin) { return ApiResponse::success((new UserResource($this->admins->permissions($admin, $request->validated('permissions'))))->resolve(), 'Permissions updated successfully.'); }
    public function resetPassword(User $admin) { return ApiResponse::success(['temporaryPassword' => $this->admins->resetPassword($admin)], 'Password reset successfully.'); }

    private function ensureAdmin(User $user): void { abort_unless(in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], true), 404); }
}

