<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\AuditService;
use App\Services\OrganizationService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class ScheduleController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}
    public function index(ApiIndexRequest $request) { $filters = $request->validated(); $paginator = Schedule::with(['days', 'people', 'departments'])->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', strtolower($status)))->orderBy('name')->paginate($filters['limit'] ?? 20); return ApiResponse::paginated($paginator, ScheduleResource::class); }
    public function show(Schedule $schedule) { return ApiResponse::success((new ScheduleResource($schedule->load(['days', 'people', 'departments'])))->resolve()); }
    public function store(StoreScheduleRequest $request) { return ApiResponse::success((new ScheduleResource($this->organization->saveSchedule(null, $request->validated())))->resolve(), 'Schedule created successfully.', 201); }
    public function update(StoreScheduleRequest $request, Schedule $schedule) { return ApiResponse::success((new ScheduleResource($this->organization->saveSchedule($schedule, $request->validated())))->resolve(), 'Schedule updated successfully.'); }
    public function destroy(Schedule $schedule, AuditService $audit) { $audit->record('schedule.deleted', $schedule, $schedule->toArray()); $schedule->delete(); return response()->noContent(); }
}

