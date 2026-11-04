<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request) { return ApiResponse::paginated($request->user()->notifications()->latest()->paginate(min((int) $request->query('limit', 20), 100)), NotificationResource::class); }
    public function unreadCount(Request $request) { return ApiResponse::success(['count' => $request->user()->unreadNotifications()->count()]); }
    public function read(Request $request, DatabaseNotification $notification) { abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === $request->user()::class, 404); $notification->markAsRead(); return ApiResponse::success(null, 'Notification marked as read.'); }
    public function readAll(Request $request) { $request->user()->unreadNotifications->markAsRead(); return ApiResponse::success(null, 'All notifications marked as read.'); }
}
