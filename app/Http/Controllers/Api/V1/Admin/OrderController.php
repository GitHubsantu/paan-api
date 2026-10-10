<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CancelOrderRequest;
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
}
