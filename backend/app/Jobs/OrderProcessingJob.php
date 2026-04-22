<?php

namespace App\Jobs;

use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OrderProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần retry nếu job thất bại.
     */
    public int $tries = 3;

    /**
     * Thời gian chờ giữa các lần retry (giây).
     */
    public int $backoff = 5;

    /**
     * Timeout tối đa cho job (giây).
     */
    public int $timeout = 60;

    public function __construct(
        public readonly int    $flashSaleId,
        public readonly int    $userId,
        public readonly int    $quantity,
        public readonly ?int   $addressId,
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        public readonly string $shippingAddress,
        public readonly string $paymentMethod,
        public readonly string $orderCode,
    ) {}

    /**
     * Xử lý tạo đơn hàng trong MySQL.
     * Chạy bất đồng bộ qua Queue Worker — không block HTTP response.
     */
    public function handle(): void
    {
        $flashSale = FlashSale::with('product')->find($this->flashSaleId);

        if (!$flashSale) {
            Log::error("[OrderProcessingJob] FlashSale #{$this->flashSaleId} không tồn tại.");
            $this->rollbackRedisStock();
            return;
        }

        DB::transaction(function () use ($flashSale) {
            $unitPrice  = $flashSale->sale_price;
            $subtotal   = $unitPrice * $this->quantity;
            $shippingFee = 0; // Flash sale: freeship
            $grandTotal = $subtotal;

            // 1. Tạo đơn hàng
            $order = Order::create([
                'order_code'       => $this->orderCode,
                'user_id'          => $this->userId,
                'address_id'       => $this->addressId,
                'recipient_name'   => $this->recipientName,
                'recipient_phone'  => $this->recipientPhone,
                'shipping_address' => $this->shippingAddress,
                'note'             => "Flash Sale #{$this->flashSaleId}",
                'payment_method'   => $this->paymentMethod,
                'payment_status'   => 'pending',
                'fulfillment_status' => 'pending',
                'subtotal'         => $subtotal,
                'discount_amount'  => 0,
                'shipping_fee'     => $shippingFee,
                'grand_total'      => $grandTotal,
                'email_sent'       => false,
            ]);

            // 2. Tạo order item từ flash sale
            OrderItem::create([
                'order_id'   => $order->order_id,
                'product_id' => $flashSale->product_id,
                'variant_id' => $flashSale->variant_id,
                'quantity'   => $this->quantity,
                'unit_price' => $unitPrice,
                'subtotal'   => $subtotal,
            ]);

            // 3. Cập nhật sold_count trong flash_sales
            FlashSale::where('id', $this->flashSaleId)
                     ->increment('sold_count', $this->quantity);

            Log::info("[OrderProcessingJob] Tạo đơn #{$this->orderCode} thành công cho user #{$this->userId}, flash_sale #{$this->flashSaleId}.");
        });
    }

    /**
     * Xử lý khi job thất bại sau tất cả lần retry.
     * QUAN TRỌNG: Hoàn trả stock về Redis để tránh thất thoát hàng.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[OrderProcessingJob] Job thất bại vĩnh viễn. Order: {$this->orderCode}. Error: {$exception->getMessage()}");
        $this->rollbackRedisStock();
    }

    /**
     * Hoàn stock về Redis khi không thể tạo đơn hàng.
     */
    private function rollbackRedisStock(): void
    {
        try {
            $key = "flash_sale_stock_{$this->flashSaleId}";
            Redis::incrby($key, $this->quantity);
            Log::info("[OrderProcessingJob] Đã hoàn {$this->quantity} stock về Redis key: {$key}");
        } catch (\Exception $e) {
            Log::critical("[OrderProcessingJob] Không thể hoàn stock Redis! Key: flash_sale_stock_{$this->flashSaleId}. Error: {$e->getMessage()}");
        }
    }
}
