<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private ShopSettingsService $shop,
        private PaymentService $payments,
    ) {}

    public function create(User $user, array $data): Order
    {
        if (! $this->shop->isOpen()) {
            throw new ApiException('Shop is currently closed.', 409);
        }

        $order = DB::transaction(function () use ($user, $data) {
            $lines = $this->priceLines($data['items']);
            $subtotal = array_sum(array_column($lines, 'line_cents'));

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'TMP'.Str::upper(Str::random(12)), // replaced below
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $this->money($subtotal),
                'total' => $this->money($subtotal), // pickup only: no fees
                'comment' => $data['comment'] ?? null,
                'placed_at' => now(),
            ]);

            $order->update(['order_number' => 'PAAN-'.(100000 + $order->id)]);

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'unit_price' => $this->money($line['unit_cents']),
                    'quantity' => $line['quantity'],
                    'line_total' => $this->money($line['line_cents']),
                ]);

                foreach ($line['addons'] as $addon) {
                    $item->addons()->create([
                        'addon_id' => $addon->id,
                        'addon_name_snapshot' => $addon->name,
                        'unit_price' => $addon->price,
                    ]);
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => null,
                'new_status' => OrderStatus::Pending,
                'changed_by' => $user->id,
                'note' => 'Order placed',
            ]);

            $this->payments->createForOrder($order, ['method' => $data['payment_method'] ?? null]);

            return $order;
        });

        Log::info('order.created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $user->id,
            'total' => (string) $order->total,
        ]);

        return $order->load(['items.addons', 'payment']);
    }

    /**
     * Validates availability/add-ons against the DB and prices everything server-side.
     *
     * @return array<int, array{product: Product, quantity: int, addons: array, unit_cents: int, line_cents: int}>
     */
    private function priceLines(array $items): array
    {
        $ids = collect($items)->pluck('product_id')->map(fn ($i) => (int) $i)->unique()->all();

        $products = Product::with(['category:id,is_active', 'addons'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $errors = [];
        $lines = [];

        foreach ($items as $i => $item) {
            $product = $products->get((int) $item['product_id']);

            if (! $product || ! $product->category?->is_active) {
                $errors["items.$i.product_id"][] = 'The selected product is unavailable.';

                continue;
            }
            if (! $product->is_available) {
                $errors["items.$i.product_id"][] = "{$product->name} is currently unavailable.";

                continue;
            }

            $allowed = $product->addons->where('is_active', true)->keyBy('id');
            $addons = [];

            foreach (array_values(array_unique($item['addon_ids'] ?? [])) as $j => $addonId) {
                $addon = $allowed->get((int) $addonId);
                if (! $addon) {
                    $errors["items.$i.addon_ids.$j"][] = "The selected add-on is not available for {$product->name}.";

                    continue;
                }
                $addons[] = $addon;
            }

            $unit = $this->cents($product->price)
                + array_sum(array_map(fn ($a) => $this->cents($a->price), $addons));
            $qty = (int) $item['quantity'];

            $lines[] = [
                'product' => $product,
                'quantity' => $qty,
                'addons' => $addons,
                'unit_cents' => $unit,
                'line_cents' => $unit * $qty,
            ];
        }

        if ($errors) {
            throw new ApiException(
                'Some items in your order are unavailable.',
                422,
                $errors,
            );
        }

        return $lines;
    }

    private function cents(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
