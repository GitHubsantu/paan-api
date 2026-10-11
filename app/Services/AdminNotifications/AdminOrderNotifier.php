<?php

namespace App\Services\AdminNotifications;

use App\Models\Order;

interface AdminOrderNotifier
{
    public function notifyNewOrder(Order $order): void;
}
