<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\ManualCheckInRequest;
use App\Http\Requests\ManualCheckOutRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceCorrectionResource;
use App\Http\Resources\AttendanceResource;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use App\Support\ApiResponse;
use Illuminate\Support\Arr;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}
    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated();
        if ($request->user()->role->value === 'user') {
            abort_unless($request->user()->person, 403);
            $filters['personId'] = $request->user()->person->person_code;
        }
        return ApiResponse::paginated($this->attendance->paginate($filters), AttendanceResource::class);
    }
    public function show(AttendanceRecord $attendance, \Illuminate\Http\Request $request) { if ($request->user()->role->value === 'user') abort_unless($request->user()->person_id === $attendance->person_id, 403); return ApiResponse::success((new AttendanceResource($attendance->load(['person.department', 'corrections', 'events.performer', 'checkedInBy', 'checkedOutBy'])))->resolve()); }
    public function store(StoreAttendanceRequest $request) { return ApiResponse::success((new AttendanceResource($this->attendance->createManual($request->validated(), $request->user())))->resolve(), 'Manual attendance created.', 201); }
    public function manual(StoreAttendanceRequest $request) { return $this->store($request); }
    public function manualCheckIn(ManualCheckInRequest $request) { return ApiResponse::success((new AttendanceResource($this->attendance->manualCheckIn($request->validated(), $request->user())))->resolve(), 'Employee checked in successfully.', 201); }
    public function manualCheckOut(ManualCheckOutRequest $request) { return ApiResponse::success((new AttendanceResource($this->attendance->manualCheckOut($request->validated(), $request->user())))->resolve(), 'Employee checked out successfully.'); }
    public function update(UpdateAttendanceRequest $request, AttendanceRecord $attendance)
    {
        $data = $request->validated(); $requested = Arr::only($data, ['checkInAt', 'checkOutAt', 'status', 'notes']);
        $correction = $this->attendance->requestCorrection($attendance, ['requestedValues' => $requested, 'reason' => $data['reason']], $request->user());
        if ($request->user()->hasPermission('attendance.approve')) $correction = $this->attendance->reviewCorrection($correction, $request->user(), true, 'Approved during authorized edit.');
        return ApiResponse::success((new AttendanceCorrectionResource($correction))->resolve(), 'Attendance change recorded with history.');
    }
    public function destroy(AttendanceRecord $attendance, \Illuminate\Http\Request $request) { $this->attendance->delete($attendance, $request->user()); return response()->noContent(); }
}
