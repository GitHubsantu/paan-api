<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BlockCustomerRequest;
use App\Http\Requests\Api\V1\Admin\CustomerIndexRequest;
use App\Http\Requests\Api\V1\Admin\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCustomerRequest;
use App\Http\Resources\AdminCustomerResource;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(private AdminAuditLogger $audit) {}

    public function index(CustomerIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $customers = User::query()->where('role', 'customer')->withCount('orders')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->latest()->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($customers, AdminCustomerResource::class, 'Customers retrieved successfully.');
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (User::onlyTrashed()->where('phone', $data['phone'])->exists()) {
            throw new ApiException('A deleted customer owns this phone number.', 409, [], 'CUSTOMER_DELETED');
        }

        $customer = User::create($data);
        $this->audit->log($request->user(), $customer, 'customer.created');

        return ApiResponse::success((new AdminCustomerResource($customer->loadCount('orders')))->resolve($request), 'Customer created successfully.', 201);
    }

    public function show(Request $request, User $customer): JsonResponse
    {
        $customer = $this->customer($customer);
        $customer->loadCount('orders');
        $recentOrders = $customer->orders()->latest()->limit(10)
            ->get(['id', 'user_id', 'order_number', 'status', 'total', 'created_at'])
            ->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ])->all();

        return ApiResponse::success([
            ...(new AdminCustomerResource($customer))->resolve($request),
            'recent_orders' => $recentOrders,
            'total_spent' => (float) $customer->orders()->where('status', '!=', OrderStatus::AdminCancelled)->sum('total'),
        ], 'Customer retrieved successfully.');
    }

    public function update(UpdateCustomerRequest $request, User $customer): JsonResponse
    {
        $customer = $this->customer($customer);
        $customer->update($request->validated());
        $this->audit->log($request->user(), $customer, 'customer.updated', ['fields' => array_keys($request->validated())]);

        return ApiResponse::success((new AdminCustomerResource($customer->fresh()->loadCount('orders')))->resolve($request), 'Customer updated successfully.');
    }

    public function block(BlockCustomerRequest $request, User $customer): JsonResponse
    {
        $customer = $this->customer($customer);
        $isActive = $request->boolean('is_active');
        $customer->forceFill(['is_active' => $isActive, 'blocked_reason' => $isActive ? null : $request->validated('reason')])->save();
        if (! $isActive) {
            $customer->tokens()->delete();
        }
        $this->audit->log($request->user(), $customer, $isActive ? 'customer.unblocked' : 'customer.blocked', ['reason' => $customer->blocked_reason]);

        return ApiResponse::success((new AdminCustomerResource($customer->fresh()->loadCount('orders')))->resolve($request), $isActive ? 'Customer unblocked successfully.' : 'Customer blocked successfully.');
    }

    public function destroy(Request $request, User $customer): Response
    {
        $customer = $this->customer($customer);
        $activeOrders = $customer->orders()->whereIn('status', [OrderStatus::Pending, OrderStatus::Making, OrderStatus::Ready])->count();
        if ($activeOrders > 0) {
            throw new ApiException('This customer has active orders.', 409, [], 'CUSTOMER_HAS_ACTIVE_ORDERS');
        }

        DB::transaction(function () use ($request, $customer): void {
            $this->audit->log($request->user(), $customer, 'customer.deleted');
            $customer->tokens()->delete();
            $customer->deviceTokens()->delete();
            $customer->delete();
        });

        return response()->noContent();
    }

    private function customer(User $customer): User
    {
        abort_if($customer->isAdmin(), 404);

        return $customer;
    }
}
