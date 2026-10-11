<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\OrderIndexRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShopSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private ShopSettingsService $shop,
    ) {}

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->validated();
        $orders = $request->user()->orders()
            ->with('items.addons')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $status === 'active'
                ? $query->whereIn('status', ['pending', 'making', 'ready'])
                : $query->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($orders, OrderResource::class, 'Orders retrieved successfully.');
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        [$order, $created] = $this->orders->create($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return ApiResponse::success(
            (new OrderResource($order))->resolve($request),
            'Order placed successfully.',
            $created ? 201 : 200
        );
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $order = $this->findCustomerOrder($request, $order, ['items.addons', 'statusHistories']);

        return ApiResponse::success(
            (new OrderResource($order))->resolve($request),
            'Order retrieved successfully.'
        );
    }

    public function status(Request $request, int $order): JsonResponse
    {
        $order = $this->findCustomerOrder($request, $order, ['statusHistories']);

        return ApiResponse::success([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'updated_at' => $order->updated_at?->toIso8601String(),
            'pickup_message' => $order->status->value === 'ready'
                ? $this->shop->all()['pickup_message']
                : null,
            'status_history' => $order->statusHistories->map(fn ($history) => [
                'status' => $history->new_status->value,
                'timestamp' => $history->created_at?->toIso8601String(),
            ])->values(),
        ], 'Order status retrieved successfully.');
    }

    /**
     * Customer ownership is applied to the query, deliberately returning 404 for another user's order.
     *
     * @param  array<int, string>  $relations
     */
    private function findCustomerOrder(Request $request, int $order, array $relations = ['items.addons']): Order
    {
        $customerOrder = $request->user()->orders()->with($relations)->findOrFail($order);

        $this->authorize('view', $customerOrder);

        return $customerOrder;
    }
}
