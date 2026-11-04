<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->string('email'), $request->string('password'), $request->string('deviceName', 'react-dashboard'));
        $result['user'] = (new UserResource($result['user']))->resolve();
        return ApiResponse::success($result, 'Signed in successfully.');
    }

    public function me(Request $request) { return ApiResponse::success((new UserResource($request->user()->load('permissions', 'person')))->resolve()); }
    public function logout(Request $request) { $this->auth->logout($request->user()); return ApiResponse::success(null, 'Signed out successfully.'); }
    public function changePassword(ChangePasswordRequest $request) { $this->auth->changePassword($request->user(), $request->string('password')); return ApiResponse::success(null, 'Password changed successfully.'); }
    public function forgotPassword(ForgotPasswordRequest $request) { return ApiResponse::success(null, $this->auth->sendResetLink($request->string('email'))); }
    public function resetPassword(ResetPasswordRequest $request) { return ApiResponse::success(null, $this->auth->resetPassword($request->validated())); }
}

