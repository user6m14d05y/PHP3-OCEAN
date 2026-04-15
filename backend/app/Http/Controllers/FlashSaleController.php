<?php

namespace App\Http\Controllers;

use App\Jobs\OrderProcessingJob;
use App\Models\FlashSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class FlashSaleController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Danh sách Flash Sale đang active
    // GET /api/flash-sale
    // ─────────────────────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        // Cache 30 giây — giảm tải DB khi nhiều user xem cùng lúc
        $data = Cache::remember('flash_sale_active_list', 30, function () {
            return FlashSale::active()
                ->with(['product:product_id,name,slug,thumbnail_url'])
                ->get()
                ->map(fn ($fs) => $this->formatFlashSale($fs));
        });

        // Nếu không có active, lấy upcoming (sắp diễn ra)
        if ($data->isEmpty()) {
            $data = Cache::remember('flash_sale_upcoming_list', 60, function () {
                return FlashSale::upcoming()
                    ->with(['product:product_id,name,slug,thumbnail_url'])
                    ->limit(3)
                    ->get()
                    ->map(fn ($fs) => $this->formatFlashSale($fs));
            });
        }

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Lấy tồn kho hiện tại từ Redis (cho Progress Bar)
    // GET /api/flash-sale/{id}/stock
    // ─────────────────────────────────────────────────────────────────────────
    public function stock(int $id): JsonResponse
    {
        $flashSale = Cache::remember("flash_sale_meta_{$id}", 60, fn () => FlashSale::find($id));

        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale không tồn tại.'], 404);
        }

        $remaining  = $flashSale->getRemainingStock();
        $soldCount  = max(0, $flashSale->total_stock - $remaining);

        return response()->json([
            'status'      => 'success',
            'flash_sale_id' => $id,
            'total_stock' => $flashSale->total_stock,
            'remaining'   => $remaining,
            'sold_count'  => $soldCount,
            'is_sold_out' => $remaining <= 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN — Nạp stock vào Redis (gọi trước khi chiến dịch bắt đầu)
    // POST /api/flash-sale/{id}/initialize  [auth:admin]
    // ─────────────────────────────────────────────────────────────────────────
    public function initialize(int $id): JsonResponse
    {
        $flashSale = FlashSale::find($id);

        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale không tồn tại.'], 404);
        }

        $flashSale->seedStockToRedis();

        // Xóa cache để force fresh
        Cache::forget("flash_sale_meta_{$id}");
        Cache::forget('flash_sale_active_list');

        Log::info("[FlashSale] Admin đã seed stock #{$id}: {$flashSale->total_stock} → Redis key: {$flashSale->stockKey()}");

        return response()->json([
            'status'  => 'success',
            'message' => "Đã nạp {$flashSale->total_stock} stock vào Redis.",
            'key'     => $flashSale->stockKey(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE — Mua Flash Sale (High-Concurrency safe)
    // POST /api/flash-sale/buy  [auth required, throttle:10,1]
    // ─────────────────────────────────────────────────────────────────────────
    public function buy(Request $request): JsonResponse
    {
        $request->validate([
            'flash_sale_id'   => 'required|integer|exists:flash_sales,id',
            'quantity'        => 'integer|min:1|max:5',
            'address_id'      => 'nullable|integer',
            'recipient_name'  => 'required|string|max:100',
            'recipient_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'payment_method'  => 'required|in:cod,vnpay,momo,banking',
        ]);

        $flashSaleId = (int) $request->flash_sale_id;
        $quantity    = (int) ($request->quantity ?? 1);
        $userId      = auth()->id();

        // 1. Kiểm tra Flash Sale có đang active không
        $flashSale = Cache::remember("flash_sale_meta_{$flashSaleId}", 30, fn () => FlashSale::find($flashSaleId));

        if (!$flashSale || $flashSale->status !== 'active') {
            return response()->json(['message' => 'Chiến dịch Flash Sale không còn hoạt động.'], 400);
        }

        if (now()->lt($flashSale->starts_at)) {
            return response()->json(['message' => 'Flash Sale chưa bắt đầu.'], 400);
        }

        if (now()->gt($flashSale->ends_at)) {
            return response()->json(['message' => 'Flash Sale đã kết thúc.'], 400);
        }

        // 2. Giới hạn mỗi user chỉ mua max_per_user
        $userPurchaseKey = "flash_sale_{$flashSaleId}_user_{$userId}";
        $userBought = (int) (Redis::get($userPurchaseKey) ?? 0);

        if ($userBought + $quantity > $flashSale->max_per_user) {
            return response()->json([
                'message' => "Mỗi khách hàng chỉ được mua tối đa {$flashSale->max_per_user} sản phẩm trong Flash Sale này.",
            ], 400);
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. ATOMIC DECREMENT — Redis single-threaded, safe với 10k concurrent
        // ─────────────────────────────────────────────────────────────────────
        $stockKey  = "flash_sale_stock_{$flashSaleId}";
        $remaining = Redis::decrby($stockKey, $quantity);

        // Nếu kết quả < 0 → Bán vượt kho → ROLLBACK và báo hết hàng
        if ($remaining < 0) {
            Redis::incrby($stockKey, $quantity); // Hoàn lại stock
            return response()->json([
                'message'  => 'Rất tiếc! Sản phẩm đã hết hàng. 😔',
                'sold_out' => true,
            ], 400);
        }

        // 4. Ghi nhận user đã mua (TTL đến hết chiến dịch)
        $ttl = max(60, now()->diffInSeconds($flashSale->ends_at));
        Redis::incrby($userPurchaseKey, $quantity);
        Redis::expire($userPurchaseKey, $ttl);

        // 5. Dispatch Job vào Queue — MySQL nhận SAU, không block response
        $orderCode = 'FS-' . strtoupper(Str::random(8));

        OrderProcessingJob::dispatch(
            flashSaleId:      $flashSaleId,
            userId:           $userId,
            quantity:         $quantity,
            addressId:        $request->address_id,
            recipientName:    $request->recipient_name,
            recipientPhone:   $request->recipient_phone,
            shippingAddress:  $request->shipping_address,
            paymentMethod:    $request->payment_method,
            orderCode:        $orderCode,
        )->onQueue('flash_sale');

        Log::info("[FlashSale] User #{$userId} mua flash_sale #{$flashSaleId} x{$quantity}. Còn lại: {$remaining}. Order: {$orderCode}");

        return response()->json([
            'status'     => 'success',
            'message'    => '🎉 Đặt hàng thành công! Đơn hàng của bạn đang được xử lý.',
            'order_code' => $orderCode,
            'remaining'  => (int) $remaining,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN — Tạo Flash Sale mới
    // POST /api/admin/flash-sale  [auth:admin]
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'     => 'required|integer|exists:products,product_id',
            'variant_id'     => 'nullable|integer',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'total_stock'    => 'required|integer|min:1|max:100000',
            'sale_price'     => 'required|numeric|min:0',
            'original_price' => 'required|numeric|min:0',
            'max_per_user'   => 'integer|min:1|max:100',
            'starts_at'      => 'required|date|after:now',
            'ends_at'        => 'required|date|after:starts_at',
            'status'         => 'in:draft,active',
        ]);

        $flashSale = FlashSale::create($data);

        // Nếu tạo với status active, seed stock vào Redis ngay
        if ($flashSale->status === 'active') {
            $flashSale->seedStockToRedis();
        }

        Cache::forget('flash_sale_active_list');
        Cache::forget('flash_sale_upcoming_list');

        return response()->json([
            'status'  => 'success',
            'message' => 'Tạo Flash Sale thành công!',
            'data'    => $this->formatFlashSale($flashSale),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER — Format response data
    // ─────────────────────────────────────────────────────────────────────────
    private function formatFlashSale(FlashSale $fs): array
    {
        return [
            'id'               => $fs->id,
            'title'            => $fs->title,
            'description'      => $fs->description,
            'product_id'       => $fs->product_id,
            'product_name'     => $fs->product?->name,
            'product_slug'     => $fs->product?->slug,
            'product_thumbnail'=> $fs->product?->thumbnail_url,  // correct column
            'sale_price'       => $fs->sale_price,
            'original_price'   => $fs->original_price,
            'discount_percent' => $fs->discount_percent,
            'total_stock'      => $fs->total_stock,
            'sold_count'       => $fs->sold_count,
            'max_per_user'     => $fs->max_per_user,
            'starts_at'        => $fs->starts_at?->toISOString(),
            'ends_at'          => $fs->ends_at?->toISOString(),
            'status'           => $fs->status,
            'server_time'      => now()->toISOString(), // Frontend dùng để sync Timer
        ];
    }
}
