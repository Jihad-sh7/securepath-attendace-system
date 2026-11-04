<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class AlertController extends Controller
{
    public function index(ApiIndexRequest $request) { $filters = $request->validated(); $paginator = Alert::with(['person', 'attendance', 'recognitionEvent', 'assignee'])->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn ($inner) => $inner->where('title', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%")->orWhere('alert_code', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', strtolower($status)))->when($filters['severity'] ?? null, fn ($q, $severity) => $q->where('severity', strtolower($severity)))->latest()->paginate($filters['limit'] ?? 20); return ApiResponse::paginated($paginator, AlertResource::class); }
    public function show(Alert $alert) { return ApiResponse::success((new AlertResource($alert->load(['person', 'attendance', 'recognitionEvent', 'assignee'])))->resolve()); }
    public function status(StatusRequest $request, Alert $alert, AuditService $audit) { $old = $alert->status->value; $alert->update(['status' => strtolower($request->string('status')), 'assigned_to' => $request->input('assignedTo', $alert->assigned_to)]); $audit->record('alert.status_updated', $alert, ['status' => $old], ['status' => $alert->status->value]); return ApiResponse::success((new AlertResource($alert->fresh(['person', 'recognitionEvent', 'assignee'])))->resolve()); }
    public function resolve(ReviewRequest $request, Alert $alert, AuditService $audit) { $alert->update(['status' => AlertStatus::Resolved, 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'metadata' => array_merge($alert->metadata ?? [], ['resolutionNote' => $request->input('note')])]); $audit->record('alert.resolved', $alert, newValues: ['status' => 'resolved']); return ApiResponse::success((new AlertResource($alert->fresh(['person', 'recognitionEvent', 'assignee'])))->resolve()); }
    public function notes(ReviewRequest $request, Alert $alert, AuditService $audit) { $notes = $alert->metadata['notes'] ?? []; $notes[] = ['text' => $request->input('note'), 'userId' => $request->user()->id, 'createdAt' => now()->toIso8601String()]; $alert->update(['metadata' => array_merge($alert->metadata ?? [], ['notes' => $notes])]); $audit->record('alert.note_added', $alert); return ApiResponse::success((new AlertResource($alert->fresh(['person', 'recognitionEvent', 'assignee'])))->resolve()); }
}
