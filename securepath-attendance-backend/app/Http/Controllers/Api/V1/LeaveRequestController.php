<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Support\ApiResponse;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}
    public function index(ApiIndexRequest $request)
    {
        $personId = $request->validated('personId');
        if ($request->user()->role->value === 'user') {
            abort_unless($request->user()->person, 403);
            $personId = $request->user()->person->person_code;
        }
        $paginator = LeaveRequest::with(['person', 'reviewer'])->when($request->validated('status'), fn ($q, $status) => $q->where('status', strtolower($status)))->when($personId, fn ($q, $code) => $q->whereHas('person', fn ($person) => $person->where('person_code', $code)))->latest()->paginate($request->integer('limit', 20));
        return ApiResponse::paginated($paginator, LeaveRequestResource::class);
    }
    public function store(StoreLeaveRequest $request) { if ($request->user()->role->value === 'user') abort_unless($request->user()->person?->person_code === $request->string('personId'), 403); return ApiResponse::success((new LeaveRequestResource($this->attendance->createLeave($request->validated())))->resolve(), 'Leave request submitted.', 201); }
    public function show(LeaveRequest $leaveRequest, \Illuminate\Http\Request $request) { if ($request->user()->role->value === 'user') abort_unless($request->user()->person_id === $leaveRequest->person_id, 403); return ApiResponse::success((new LeaveRequestResource($leaveRequest->load(['person', 'reviewer'])))->resolve()); }
    public function approve(ReviewRequest $request, LeaveRequest $leaveRequest) { return ApiResponse::success((new LeaveRequestResource($this->attendance->reviewLeave($leaveRequest, $request->user(), true, $request->input('reviewNote', $request->input('note')))))->resolve(), 'Leave approved.'); }
    public function reject(ReviewRequest $request, LeaveRequest $leaveRequest) { return ApiResponse::success((new LeaveRequestResource($this->attendance->reviewLeave($leaveRequest, $request->user(), false, $request->input('reviewNote', $request->input('note')))))->resolve(), 'Leave rejected.'); }
    public function destroy(LeaveRequest $leaveRequest, AuditService $audit, \Illuminate\Http\Request $request) { if ($request->user()->role->value === 'user') abort_unless($request->user()->person_id === $leaveRequest->person_id, 403); abort_unless(in_array($leaveRequest->status, [LeaveStatus::Pending, LeaveStatus::Cancelled], true), 409); $leaveRequest->update(['status' => LeaveStatus::Cancelled]); $audit->record('leave.cancelled', $leaveRequest); return response()->noContent(); }
}
