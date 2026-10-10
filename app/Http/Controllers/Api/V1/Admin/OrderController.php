<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CancelOrderRequest;
use App\Http\Requests\Api\V1\Admin\OrderIndexRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private OrderStatusService $statuses) {}

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->statuses->transition($order, OrderStatus::from($request->validated('status')), $request->user(), $request->validated('note'));

        return ApiResponse::success((new OrderResource($order))->resolve($request), 'Order status updated successfully.');
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->statuses->cancel($order, $request->user(), $request->validated('reason'));

        return ApiResponse::success((new OrderResource($order))->resolve($request), 'Order cancelled successfully.');
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $orders = Order::query()->with('user:id,name,phone')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })->latest()->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($orders, OrderResource::class, 'Orders retrieved successfully.');
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user:id,name,phone', 'items.addons', 'payment', 'statusHistories']);

        return ApiResponse::success([
            'order' => (new OrderResource($order))->resolve(request()),
            'customer' => ['name' => $order->user->name, 'phone' => $order->user->phone],
            'status_history' => $order->statusHistories->map(fn ($history) => ['status' => $history->new_status->value, 'timestamp' => $history->created_at?->toIso8601String()])->values(),
        ], 'Order retrieved successfully.');
    }
}
