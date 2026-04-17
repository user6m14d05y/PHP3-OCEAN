<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Models\ProductVariant;
use App\Models\Payment;
use App\Services\VNPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Lấy user id hiện tại
     */
    private function getUserId()
    {
        $user = auth('api')->user();
        if ($user) return $user->user_id;

        if (auth('admin')->check()) {
            return auth('admin')->user()->getKey();
        }

        return null;
    }

    /**
     * Khách hàng: Xem danh sách đơn hàng của mình
     */
    public function index(Request $request)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $query = Order::with(['items.product', 'items.variant', 'items.comment'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Có thể lọc theo status (pending, completed, v.v.)
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('fulfillment_status', $request->status);
        }

        $orders = $query->paginate(10);

        // Thêm flag is_reviewed cho mỗi order
        $orders->getCollection()->transform(function ($order) {
            // Mới: Một đơn hàng chỉ được coi là ĐÃ ĐÁNH GIÁ (is_reviewed=true) nếu TẤT CẢ sản phẩm đã được đánh giá
            // Nếu có ít nhất 1 sản phẩm chưa được đánh giá thì vẫn hiện nút Đánh giá
            $order->is_reviewed = $order->items->every(fn($item) => $item->comment !== null);
            return $order;
        });

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    /**
     * Khách hàng: Đặt hàng (Checkout)
     */
    public function store(Request $request)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập để đặt hàng!'], 401);
        }

        $request->validate([
            'address_id' => 'required_without:recipient_name|nullable|exists:addresses,address_id',
            'recipient_name' => 'required_without:address_id|nullable|string|max:255',
            'phone' => 'required_without:address_id|nullable|string|max:20',
            'province' => 'required_without:address_id|nullable|string|max:100',
            'district' => 'required_without:address_id|nullable|string|max:100',
            'ward' => 'required_without:address_id|nullable|string|max:100',
            'address_line' => 'required_without:address_id|nullable|string|max:255',
            'payment_method' => 'required|in:cod,vnpay,momo,bank_transfer',
            'coupon_applied' => 'nullable|string',
            'note' => 'nullable|string|max:500'
        ]);

        if ($request->address_id) {
            $address = Address::where('address_id', $request->address_id)
                ->where('user_id', $userId)
                ->first();

            if (!$address) {
                return response()->json(['status' => 'error', 'message' => 'Địa chỉ không hợp lệ!'], 400);
            }
        } else {
            $address = Address::create([
                'user_id' => $userId,
                'recipient_name' => $request->recipient_name,
                'phone' => $request->phone,
                'province' => $request->province,
                'district' => $request->district,
                'ward' => $request->ward,
                'address_line' => $request->address_line,
                'province_code' => $request->province_code,
                'district_code' => $request->district_code,
                'ward_code' => $request->ward_code,
                'is_default' => false,
            ]);
        }

        // Lấy giỏ hàng active
        $cart = Cart::where('user_id', $userId)->where('status', 'active')->first();
        if (!$cart) {
            return response()->json(['status' => 'error', 'message' => 'Giỏ hàng trống!'], 400);
        }

        // Lấy các sản phẩm được chọn
        $cartItems = CartItem::with(['variant.product'])
            ->where('cart_id', $cart->cart_id)
            ->where('selected', true)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Vui lòng chọn sản phẩm để thanh toán!'], 400);
        }

        // Tính toán tổng tiền
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item->variant->price * $item->quantity;
            // Kiểm tra tồn kho
            if ($item->variant->stock < $item->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sản phẩm ' . $item->variant->product->name . ' không đủ tồn kho!'
                ], 400);
            }
        }

        // Xử lý mã giảm giá
        $discountAmount = 0;
        $couponId = null;
        if ($request->coupon_applied) {
            $coupon = Coupon::where('code', $request->coupon_applied)
                ->where('is_active', true)
                ->first();

            if ($coupon) {
                // Kiểm tra điều kiện áp dụng mã
                $now = now();
                if ($coupon->start_date && $now->lt($coupon->start_date)) {
                    return response()->json(['status' => 'error', 'message' => 'Mã giảm giá chưa đến thời gian áp dụng!'], 400);
                }
                if ($coupon->end_date && $now->gt($coupon->end_date)) {
                    return response()->json(['status' => 'error', 'message' => 'Mã giảm giá đã hết hạn!'], 400);
                }
                if ($coupon->min_order_value && $subtotal < $coupon->min_order_value) {
                    return response()->json(['status' => 'error', 'message' => 'Đơn hàng không đạt giá trị tối thiểu để áp dụng mã này!'], 400);
                }
                if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                    return response()->json(['status' => 'error', 'message' => 'Mã giảm giá đã hết lượt sử dụng!'], 400);
                }

                // Nếu có giới hạn dùng mỗi user (usage_limit_per_user), cần check user_coupons
                if ($coupon->usage_limit_per_user !== null) {
                    $userUsedCount = \App\Models\UserCoupon::where('user_id', $userId)
                        ->where('coupon_id', $coupon->id)
                        ->value('used_count') ?? 0;
                    if ($userUsedCount >= $coupon->usage_limit_per_user) {
                        return response()->json(['status' => 'error', 'message' => 'Bạn đã hết lượt sử dụng mã này!'], 400);
                    }
                }

                // Tính toán giảm giá
                if ($coupon->type === 'percent') {
                    $disc = ($subtotal * $coupon->value) / 100;
                    if ($coupon->max_discount_value) {
                        $disc = min($disc, $coupon->max_discount_value);
                    }
                    $discountAmount = $disc;
                } elseif ($coupon->type === 'fixed') {
                    $discountAmount = min($coupon->value, $subtotal);
                } elseif ($coupon->type === 'free_ship') {
                    // Xử lý freeship sau ở phần phí vận chuyển
                }

                // Thu nhỏ discountAmount sao cho không vượt subtotal
                $discountAmount = min($discountAmount, $subtotal);
                $couponId = $coupon->id;
            }
        }

        // Tính phí vận chuyển động
        $shippingFee = 30000; // Mặc định nếu không tìm thấy

        // Tính phí vận chuyển động qua GHN API
        $shippingFee = 30000; // Mặc định nếu API lỗi hoặc không gọi được

        if (env('VITE_TOKEN_GHN') && $address->district_code && $address->ward_code) {
            try {
                $ghnResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'Token' => env('VITE_TOKEN_GHN'),
                    'ShopId' => env('VITE_SHOPID_GHN')
                ])->get('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', [
                    'service_type_id' => 2,
                    'to_district_id' => (int) $address->district_code,
                    'to_ward_code' => $address->ward_code,
                    'weight' => 3000,
                ]);

                if ($ghnResponse->successful()) {
                    $json = $ghnResponse->json();
                    if (isset($json['data']['total'])) {
                        $shippingFee = $json['data']['total'];
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('GHN Fee API Error: ' . $e->getMessage());
            }
        }

        // Miễn phí vận chuyển nếu đơn hàng đạt ngưỡng freeship
        $freeshipThreshold = (int) config('shop.freeship_threshold', 500000);
        if ($subtotal >= $freeshipThreshold) {
            $shippingFee = 0; // Đơn hàng đạt mốc freeship
        }

        // Coupon free_ship cũng miễn phí ship (ưu tiên nếu chưa được miễn)
        if (isset($coupon) && $coupon->type === 'free_ship') {
            $shippingFee = 0; // Áp dụng coupon freeship
        }

        $grandTotal = $subtotal + $shippingFee - $discountAmount;

        // Bắt đầu transaction
        DB::beginTransaction();
        try {
            // Khóa các variants để ngăn race condition (đặt hàng đồng thời vượt quá tồn kho)
            $variantIds = $cartItems->pluck('variant_id');
            $lockedVariants = ProductVariant::whereIn('variant_id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('variant_id');

            // Kiểm tra lại tồn kho sau khi đã khóa row
            foreach ($cartItems as $cItem) {
                $lockedVariant = $lockedVariants[$cItem->variant_id] ?? null;
                if (!$lockedVariant || $lockedVariant->stock < $cItem->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sản phẩm ' . $cItem->variant->product->name . ' đã hết hàng khi bạn đặt mua!'
                    ], 400);
                }
            }

            // Chuỗi địa chỉ
            $fullAddress = implode(', ', array_filter([
                $address->address_line,
                $address->ward,
                $address->district,
                $address->province
            ]));

            // Tạo order
            $order = Order::create([
                'order_code' => 'ORD' . strtoupper(uniqid()) . rand(10, 99),
                'user_id' => $userId,
                'address_id' => $address->address_id,
                'promotion_id' => $couponId,
                'recipient_name' => $address->recipient_name,
                'recipient_phone' => $address->phone,
                'shipping_address' => $fullAddress,
                'note' => $request->note,
                'payment_method' => $request->payment_method,
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee,
                'grand_total' => $grandTotal,
            ]);

            // Tạo order items và cập nhật tồn kho
            foreach ($cartItems as $cItem) {
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $cItem->variant->product_id,
                    'variant_id' => $cItem->variant_id,
                    'product_name' => $cItem->variant->product->name,
                    'variant_name' => $cItem->variant->variant_name,
                    'sku' => $cItem->variant->sku,
                    'color' => $cItem->variant->color,
                    'size' => $cItem->variant->size,
                    'quantity' => $cItem->quantity,
                    'unit_price' => $cItem->variant->price,
                    'line_total' => $cItem->variant->price * $cItem->quantity,
                ]);

                // Trừ tồn kho
                $cItem->variant->decrement('stock', $cItem->quantity);
            }

            // Ghi nhận lịch sử
            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'new_status' => 'pending',
                'note' => 'Khách hàng đặt đơn hàng mới',
            ]);

            // Cập nhật lượt dùng coupon nếu có
            if ($couponId) {
                $coupon->increment('used_count');
                // Lưu vào user_coupons
                $userCoupon = UserCoupon::where('user_id', $userId)->where('coupon_id', $couponId)->first();
                if ($userCoupon) {
                    $userCoupon->increment('used_count');
                } else {
                    UserCoupon::create([
                        'user_id' => $userId,
                        'coupon_id' => $couponId,
                        'used_count' => 1,
                        'is_saved' => false
                    ]);
                }
            }

            // Xóa các sản phẩm đã chọn khỏi giỏ
            CartItem::whereIn('cart_item_id', $cartItems->pluck('cart_item_id'))->delete();

            // ==================== XỬ LÝ VNPAY ====================
            // [FIX P1] Di chuyển VNPay logic vào TRƯỚC DB::commit()
            // Nếu createPaymentUrl() fail → order vẫn commit, trả warning để user retry
            if ($request->payment_method === 'vnpay') {
                // Tạo Payment record với status pending (nằm trong transaction)
                Payment::create([
                    'order_id' => $order->order_id,
                    'payment_method' => 'vnpay',
                    'amount' => $order->grand_total,
                    'status' => 'pending',
                ]);

                // Generate VNPay payment URL — wrap try-catch riêng
                try {
                    $ipAddr = $request->ip();
                    $vnpayUrl = VNPayService::createPaymentUrl($order, $ipAddr);
                } catch (\Exception $e) {
                    // VNPay URL generation failed → vẫn commit order, trả warning
                    Log::error('VNPay URL generation failed: ' . $e->getMessage());
                    DB::commit();
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Đơn hàng đã tạo nhưng không thể kết nối VNPay. Vui lòng vào "Đơn hàng của tôi" để thử thanh toán lại.',
                        'data' => [
                            'order_code' => $order->order_code,
                            'grand_total' => $order->grand_total
                        ]
                    ]);
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Đơn hàng đã tạo. Đang chuyển đến cổng thanh toán VNPay...',
                    'payment_method' => 'vnpay',
                    'vnpay_url' => $vnpayUrl,
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total
                    ]
                ]);
            }

            // ==================== XỬ LÝ MOMO ====================
            if ($request->payment_method === 'momo') {
                Payment::create([
                    'order_id' => $order->order_id,
                    'payment_method' => 'momo',
                    'amount' => $order->grand_total,
                    'status' => 'pending',
                ]);

                try {
                    $momoUrl = \App\Services\MoMoService::createPaymentUrl($order);
                } catch (\Exception $e) {
                    Log::error('MoMo URL generation failed: ' . $e->getMessage());
                    DB::commit();
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Đơn hàng đã tạo nhưng không thể kết nối MoMo. Vui lòng thử thanh toán lại sau.',
                        'data' => [
                            'order_code' => $order->order_code,
                            'grand_total' => $order->grand_total
                        ]
                    ]);
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Đơn hàng đã tạo. Đang chuyển đến cổng thanh toán MoMo...',
                    'payment_method' => 'momo',
                    'momo_url' => $momoUrl,
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total
                    ]
                ]);
            }

            // ==================== FLOW MẶC ĐỊNH (COD, Bank) ====================
            DB::commit();
            // Fire realtime event for admin (bọc try-catch để tránh treo thanh toán nếu websocket lỗi)
            try {
                event(new \App\Events\OrderCreatedAdmin($order));
            } catch (\Exception $e) {
                Log::error('Realtime event dispatch failed: ' . $e->getMessage());
            }

            // Email xác nhận đơn hàng → KHÔNG gửi đồng bộ nữa
            // Cron job "app:send-order-emails" sẽ tự động gửi sau 5 phút
            // → Response trả về nhanh hơn (giảm 3-10 giây chờ SMTP)

            return response()->json([
                'status' => 'success',
                'message' => 'Đặt hàng thành công!',
                'data' => [
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại sau.'
            ], 500);
        }
    }

    /**
     * Khách hàng: Xem chi tiết đơn hàng
     */
    public function show($id)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $order = Order::with(['items.product.images', 'items.variant', 'statusHistories'])
            ->where('user_id', $userId)
            ->where('order_id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    /**
     * Khách hàng: Hủy đơn hàng
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'Vui lòng chọn hoặc nhập lý do hủy đơn hàng.',
        ]);

        $userId = $this->getUserId();
        if (!$userId) return response()->json(['status' => 'error'], 401);

        $order = Order::where('order_id', $id)->where('user_id', $userId)->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        if ($order->fulfillment_status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Bạn chỉ có thể hủy đơn hàng khi đang chờ xác nhận!'], 400);
        }

        $cancelReason = $request->cancel_reason;

        DB::beginTransaction();
        try {
            $order->update([
                'fulfillment_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $cancelReason
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'old_status' => 'pending',
                'new_status' => 'cancelled',
                'note' => 'Khách hàng hủy đơn: ' . $cancelReason,
            ]);

            // Hoàn lại tồn kho (N+1 query fix)
            $orderItems = OrderItem::where('order_id', $order->order_id)->get();
            $cases = [];
            $bindings = [];
            $variantIds = [];

            foreach ($orderItems as $item) {
                $cases[] = "WHEN ? THEN stock + ?";
                $bindings[] = $item->variant_id;
                $bindings[] = $item->quantity;
                $variantIds[] = $item->variant_id;
            }

            if (!empty($variantIds)) {
                $ids = implode(',', array_fill(0, count($variantIds), '?'));
                $casesSql = implode(' ', $cases);
                $bindings = array_merge($bindings, $variantIds);
                DB::statement("UPDATE product_variants SET stock = CASE variant_id {$casesSql} END, updated_at = NOW() WHERE variant_id IN ({$ids})", $bindings);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã hủy đơn hàng thành công!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancel error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi hủy đơn.'], 500);
        }
    }
    /**
     * Lấy ID đơn hàng từ order_code (thay vì ID cho Vue router params)
     */
    public function getOrderIdByCode($order_code)
    {
        $userId = $this->getUserId();
        if (!$userId) return response()->json(['status' => 'error'], 401);

        $order = Order::where('order_code', $order_code)->where('user_id', $userId)->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->order_id
            ]
        ]);
    }
}
