<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;

class AdminDashboardService
{
    public function summary(): array
    {
        $today = today();
        $paidOrders = Order::query()->where('payment_status', PaymentStatus::Paid);
        $revenueOrders = (clone $paidOrders)->where('status', '!=', OrderStatus::AdminCancelled);

        return [
            'today_orders' => (clone $paidOrders)->whereDate('created_at', $today)->count(),
            'status_counts' => collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => Order::where('status', $status)->where('payment_status', PaymentStatus::Paid)->whereDate('created_at', $today)->count()])->all(),
            'today_sales' => (float) ((clone $revenueOrders)->whereDate('created_at', $today)->sum('total')),
            'total_sales' => (float) $revenueOrders->sum('total'),
            'total_customers' => User::where('role', 'customer')->count(),
        ];
    }
}
