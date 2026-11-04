<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PersonResource;
use App\Models\Department;
use App\Services\DashboardService;
use App\Services\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class DepartmentController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}
    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated(); $paginator = Department::with(['manager', 'schedules'])->withCount('people')->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', strtolower($status)))->orderBy('name')->paginate($filters['limit'] ?? 20);
        return ApiResponse::paginated($paginator, DepartmentResource::class);
    }
    public function show(Department $department) { return ApiResponse::success((new DepartmentResource($department->load(['manager', 'schedules'])->loadCount('people')))->resolve()); }
    public function store(StoreDepartmentRequest $request) { return ApiResponse::success((new DepartmentResource($this->organization->saveDepartment(null, $request->validated())))->resolve(), 'Department created successfully.', 201); }
    public function update(StoreDepartmentRequest $request, Department $department) { return ApiResponse::success((new DepartmentResource($this->organization->saveDepartment($department, $request->validated())))->resolve(), 'Department updated successfully.'); }
    public function destroy(Department $department) { $department->update(['status' => 'inactive']); $department->delete(); return response()->noContent(); }
    public function members(ApiIndexRequest $request, Department $department) { return ApiResponse::paginated($department->people()->with(['department', 'role', 'biometricProfile'])->paginate($request->integer('limit', 20)), PersonResource::class); }
    public function statistics(Department $department, DashboardService $dashboard) { return ApiResponse::success(collect($dashboard->departmentStats(today()))->firstWhere('id', $department->code)); }
}
