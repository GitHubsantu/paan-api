<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name_snapshot', 'product_image_snapshot', 'unit_price', 'quantity', 'line_total', 'options'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'options' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }
}
