<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

class AuditLogController extends Controller
{
    public function index(ApiIndexRequest $request)
    {
        $filters = $request->validated();
        $paginator = AuditLog::with('user')->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $inner) => $inner->where('action', 'like', "%{$search}%")->orWhere('resource_type', 'like', "%{$search}%")->orWhere('resource_id', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn (Builder $q, string $result) => $q->where('result', strtolower($result)))->latest()->paginate($filters['limit'] ?? 20);
        return ApiResponse::paginated($paginator, AuditLogResource::class);
    }
    public function show(AuditLog $auditLog) { return ApiResponse::success((new AuditLogResource($auditLog->load('user')))->resolve()); }
}

