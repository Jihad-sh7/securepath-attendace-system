<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Department;
use App\Models\Holiday;
use App\Services\AuditService;
use App\Support\ApiResponse;

class HolidayController extends Controller
{
    public function index() { return ApiResponse::success(HolidayResource::collection(Holiday::with('department')->orderBy('date')->get())->resolve()); }
    public function store(StoreHolidayRequest $request, AuditService $audit) { $data = $request->validated(); $holiday = Holiday::create(['name' => $data['name'], 'date' => $data['date'], 'department_id' => isset($data['departmentId']) ? Department::where('code', $data['departmentId'])->value('id') : null, 'description' => $data['description'] ?? null]); $audit->record('holiday.created', $holiday, newValues: $holiday->toArray()); return ApiResponse::success((new HolidayResource($holiday->load('department')))->resolve(), 'Holiday created.', 201); }
    public function update(StoreHolidayRequest $request, Holiday $holiday, AuditService $audit) { $old = $holiday->toArray(); $data = $request->validated(); $holiday->update(['name' => $data['name'], 'date' => $data['date'], 'department_id' => isset($data['departmentId']) ? Department::where('code', $data['departmentId'])->value('id') : null, 'description' => $data['description'] ?? null]); $audit->record('holiday.updated', $holiday, $old, $holiday->toArray()); return ApiResponse::success((new HolidayResource($holiday->load('department')))->resolve()); }
    public function destroy(Holiday $holiday, AuditService $audit) { $audit->record('holiday.deleted', $holiday, $holiday->toArray()); $holiday->delete(); return response()->noContent(); }
}
