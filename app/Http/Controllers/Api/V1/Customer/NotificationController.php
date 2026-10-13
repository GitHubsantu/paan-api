<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Models\CustomerNotification;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $notifications = CustomerNotification::query()
            ->forUser($request->user())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20, page: $filters['page'] ?? 1);

        $response = ApiResponse::paginated($notifications, NotificationResource::class, 'Notifications retrieved successfully.');
        $payload = $response->getData(true);
        $payload['meta']['unread_count'] = $this->query($request)->whereNull('read_at')->count();

        return response()->json($payload);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->ensureCustomer($request);

        return ApiResponse::success(['unread_count' => $this->query($request)->whereNull('read_at')->count()]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $this->ensureCustomer($request);
        $notification = $this->notification($request, $id);
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success([
            ...(new NotificationResource($notification->fresh()))->resolve($request),
            'unread_count' => $this->query($request)->whereNull('read_at')->count(),
        ], 'Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->ensureCustomer($request);
        $updated = $this->query($request)->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::success(['updated' => $updated, 'unread_count' => 0], 'Notifications marked as read.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->ensureCustomer($request);
        $this->notification($request, $id)->delete();

        return ApiResponse::success(['unread_count' => $this->query($request)->whereNull('read_at')->count()], 'Notification deleted successfully.');
    }

    private function ensureCustomer(Request $request): void
    {
        abort_unless($request->user()?->tokenCan('customer'), 403);
    }

    private function notification(Request $request, string $id): CustomerNotification
    {
        $notification = $this->query($request)->find($id);
        if (! $notification) {
            throw new ApiException('Notification not found.', 404);
        }

        return $notification;
    }

    private function query(Request $request): Builder
    {
        return CustomerNotification::query()->forUser($request->user());
    }
}
