<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}
    public function index(Request $request) { return ApiResponse::success($this->dashboard->dashboard($request->query('date'))); }
    public function summary(Request $request) { return ApiResponse::success($this->dashboard->dashboard($request->query('date'))['stats']); }
    public function attendanceTrend(Request $request) { return ApiResponse::success($this->dashboard->attendanceTrend(Carbon::parse($request->query('date', today())))); }
    public function attendanceByDepartment(Request $request) { return ApiResponse::success($this->dashboard->departmentStats(Carbon::parse($request->query('date', today())))); }
    public function recentActivity(Request $request) { return ApiResponse::success($this->dashboard->dashboard($request->query('date'))['recentActivity']); }
}

