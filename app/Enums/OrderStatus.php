<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Making = 'making';
    case Ready = 'ready';
    case Completed = 'completed';
    case AdminCancelled = 'admin_cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Making, self::AdminCancelled],
            self::Making => [self::Ready, self::AdminCancelled],
            self::Ready => [self::Completed, self::AdminCancelled],
            self::Completed, self::AdminCancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
