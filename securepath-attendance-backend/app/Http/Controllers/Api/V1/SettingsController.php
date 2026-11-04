<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Services\SettingsService;
use App\Support\ApiResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}
    public function index() { return ApiResponse::success($this->settings->all()); }
    public function update(SettingsRequest $request) { return ApiResponse::success($this->settings->update($request->validated('settings'), $request->user()->id), 'Settings updated successfully.'); }
}

