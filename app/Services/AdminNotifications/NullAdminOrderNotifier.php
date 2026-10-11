<?php

namespace App\Services\AdminNotifications;

use App\Models\Order;

class NullAdminOrderNotifier implements AdminOrderNotifier
{
    public function notifyNewOrder(Order $order): void {}
}
