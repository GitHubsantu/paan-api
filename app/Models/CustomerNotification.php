<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'type', 'title', 'body', 'order_id', 'status', 'read_at', 'notifiable_type', 'notifiable_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @param Builder<CustomerNotification> $query */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->orWhere(function (Builder $query) use ($user): void {
                $query->where('notifiable_type', User::class)->where('notifiable_id', (string) $user->id);
            });
    }
}
