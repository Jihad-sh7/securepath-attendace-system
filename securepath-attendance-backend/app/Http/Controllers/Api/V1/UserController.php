<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Person;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated();
        $paginator = User::with(['permissions', 'person'])->where('role', UserRole::User)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', strtolower($status)))->orderBy('name')->paginate($filters['limit'] ?? 20);
        return ApiResponse::paginated($paginator, UserResource::class);
    }

    public function show(User $user) { $this->ensureRegular($user); return ApiResponse::success((new UserResource($user->load('permissions', 'person')))->resolve()); }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'role' => UserRole::User, 'status' => $data['status'] ?? 'active', 'title' => $data['title'] ?? 'Employee', 'person_id' => isset($data['personId']) ? Person::where('person_code', $data['personId'])->value('id') : null, 'two_factor_enabled' => $data['twoFactorEnabled'] ?? false]);
        if ($permission = Permission::where('name', 'myAttendance.view')->first()) $user->permissions()->attach($permission);
        $this->audit->record('user.created', $user, newValues: $user->only(['name', 'email', 'status']));
        return ApiResponse::success((new UserResource($user->load('permissions', 'person')))->resolve(), 'User created successfully.', 201);
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $this->ensureRegular($user); $data = $request->validated(); $old = $user->toArray();
        $mapped = [];
        foreach (['name' => 'name', 'phone' => 'phone', 'title' => 'title', 'status' => 'status', 'twoFactorEnabled' => 'two_factor_enabled'] as $source => $target) if (array_key_exists($source, $data)) $mapped[$target] = $data[$source];
        if (array_key_exists('email', $data)) $mapped['email'] = strtolower($data['email']);
        if (! empty($data['password'])) $mapped['password'] = $data['password'];
        if (array_key_exists('personId', $data)) $mapped['person_id'] = $data['personId'] ? Person::where('person_code', $data['personId'])->value('id') : null;
        $user->update($mapped); $this->audit->record('user.updated', $user, $old, $user->toArray());
        return ApiResponse::success((new UserResource($user->fresh(['permissions', 'person'])))->resolve(), 'User updated successfully.');
    }

    public function status(StatusRequest $request, User $user) { $this->ensureRegular($user); $user->update(['status' => $request->string('status')]); $user->tokens()->delete(); return ApiResponse::success((new UserResource($user->load('permissions')))->resolve()); }
    public function destroy(User $user) { $this->ensureRegular($user); $this->audit->record('user.deleted', $user, $user->toArray()); $user->tokens()->delete(); $user->delete(); return response()->noContent(); }
    private function ensureRegular(User $user): void { abort_unless($user->role === UserRole::User, 404); }
}
