<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\AttendanceCorrectionResource;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use App\Support\ApiResponse;

class AttendanceCorrectionController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}
    public function index(ApiIndexRequest $request) { $paginator = AttendanceCorrection::with(['attendance.person', 'requester', 'reviewer'])->when($request->validated('status'), fn ($q, $status) => $q->where('status', strtolower($status)))->latest()->paginate($request->integer('limit', 20)); return ApiResponse::paginated($paginator, AttendanceCorrectionResource::class); }
    public function store(AttendanceCorrectionRequest $request, AttendanceRecord $attendance) { if ($request->user()->role->value === 'user') abort_unless($request->user()->person_id === $attendance->person_id, 403); return ApiResponse::success((new AttendanceCorrectionResource($this->attendance->requestCorrection($attendance, $request->validated(), $request->user())))->resolve(), 'Correction requested.', 201); }
    public function show(AttendanceCorrection $attendanceCorrection) { return ApiResponse::success((new AttendanceCorrectionResource($attendanceCorrection->load(['attendance', 'requester', 'reviewer'])))->resolve()); }
    public function approve(ReviewRequest $request, AttendanceCorrection $attendanceCorrection) { return ApiResponse::success((new AttendanceCorrectionResource($this->attendance->reviewCorrection($attendanceCorrection, $request->user(), true, $request->input('reviewNote', $request->input('note')))))->resolve(), 'Correction approved.'); }
    public function reject(ReviewRequest $request, AttendanceCorrection $attendanceCorrection) { return ApiResponse::success((new AttendanceCorrectionResource($this->attendance->reviewCorrection($attendanceCorrection, $request->user(), false, $request->input('reviewNote', $request->input('note')))))->resolve(), 'Correction rejected.'); }
}
