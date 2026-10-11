<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;

class AdminAuditLogger
{
    /** @param array<string, mixed> $context */
    public function log(User $admin, ?User $customer, string $action, array $context = []): void
    {
        AdminActivityLog::create([
            'admin_id' => $admin->id,
            'customer_id' => $customer?->id,
            'action' => $action,
            'context' => $context,
        ]);
    }
}
