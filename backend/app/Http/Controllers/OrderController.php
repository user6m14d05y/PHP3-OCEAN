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

        $query = Order::with(['items.product', 'items.variant' => function ($q) {
                // để lấy hình ảnh riêng của variant nếu có
            }])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');
            
        // Có thể lọc theo status (pending, completed, v.v.)
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('fulfillment_status', $request->status);
        }

        $orders = $query->paginate(10);

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

        // Tính phí vận chuyển động dựa trên ShippingZone
        $shippingFee = 30000; // Mặc định nếu không tìm thấy
        $zones = \App\Models\ShippingZone::where('is_active', true)
            ->orderByDesc('priority')
            ->get();
            
        $matchedZone = null;
        foreach ($zones as $zone) {
            if (empty($zone->provinces)) {
                if (!$matchedZone) $matchedZone = $zone; // Fallback
                continue;
            }
            
            // Đảm bảo provinces là mảng (Xử lý trường hợp DB lưu là json string hoặc comma-separated)
            $provincesArray = [];
            if (is_array($zone->provinces)) {
                $provincesArray = $zone->provinces;
            } elseif (is_string($zone->provinces)) {
                $decoded = json_decode($zone->provinces, true);
                if (is_array($decoded)) {
                    $provincesArray = $decoded;
                } else {
                    $provincesArray = array_map('trim', explode(',', $zone->provinces));
                }
            }

            $inProvince = false;
            foreach ($provincesArray as $p) {
                if (empty($p)) continue;
                $provName = mb_strtolower($p, 'UTF-8');
                $addrProv = mb_strtolower($address->province ?? '', 'UTF-8');
                $addrDist = $address->district ? mb_strtolower($address->district, 'UTF-8') : '';
                
                if (str_contains($addrProv, $provName) || str_contains($provName, $addrProv) ||
                    ($addrDist && (str_contains($addrDist, $provName) || str_contains($provName, $addrDist)))) {
                    $inProvince = true;
                    break;
                }
            }
            
            if ($inProvince) {
                $matchedZone = $zone;
                break;
            }
        }
        
        if ($matchedZone) {
            if ($matchedZone->free_ship_threshold && $subtotal >= $matchedZone->free_ship_threshold) {
                $shippingFee = 0;
            } else {
                $shippingFee = $matchedZone->shipping_fee;
            }
        }

        if (isset($coupon) && $coupon->type === 'free_ship') {
            $shippingFee = 0; // Áp dụng coupon freeship
        }

        $grandTotal = $subtotal + $shippingFee - $discountAmount;

        // Bắt đầu transaction
        DB::beginTransaction();
        try {
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

            // ==================== FLOW MẶC ĐỊNH (COD, Bank, MoMo) ====================
            DB::commit();
            // Fire realtime event for admin (bọc try-catch để tránh treo thanh toán nếu websocket lỗi)
            try {
                event(new \App\Events\OrderCreatedAdmin($order));
            } catch (\Exception $e) {
                Log::error('Realtime event dispatch failed: ' . $e->getMessage());
            }

            // Gửi email
            $this->sendOrderConfirmationEmail($order);

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

            // Hoàn lại tồn kho
            $orderItems = OrderItem::where('order_id', $order->order_id)->get();
            foreach ($orderItems as $item) {
                ProductVariant::where('variant_id', $item->variant_id)->increment('stock', $item->quantity);
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
     * Gửi email xác nhận đơn hàng 
     */
    private function sendOrderConfirmationEmail(Order $order): bool
    {
        try {
            $user = auth('api')->user() ?? auth('admin')->user();
            if (!$user || empty($user->email)) return false;

            $emailUser = env('EMAIL_USER');
            $emailPass = env('EMAIL_PASS');

            if (!$emailUser || !$emailPass) {
                Log::warning('Skip sending email as EMAIL_USER missing.');
                return false;
            }

            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                'smtp.gmail.com',
                587,
                false
            );
            $transport->setUsername($emailUser);
            $transport->setPassword($emailPass);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $order->load('items');

            $itemsHtml = '';
            foreach ($order->items as $item) {
                $variantInfo = $item->variant_name ? '(' . $item->color . '/' . $item->size . ')' : '';
                $itemsHtml .= '
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item->product_name) . ' ' . $variantInfo . ' x' . $item->quantity . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">' . number_format($item->line_total, 0, ',', '.') . 'đ</td>
                </tr>';
            }

            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div style="background: #0288d1; padding: 20px; text-align: center; color: white;">
                        <h2 style="margin: 0;">Cảm ơn bạn đã đặt hàng!</h2>
                        <p style="margin: 5px 0 0;">Đơn hàng của bạn đã được ghi nhận</p>
                    </div>
                    <div style="padding: 20px;">
                        <p>Xin chào <strong>' . htmlspecialchars($order->recipient_name) . '</strong>,</p>
                        <p>Ocean Store xin thông báo đơn hàng <strong>' . $order->order_code . '</strong> của bạn đã được tạo thành công vào lúc ' . now()->format('H:i d/m/Y') . '.</p>
                        
                        <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 5px; color: #333;">Chi tiết đơn hàng</h3>
                        <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 20px;">
                            ' . $itemsHtml . '
                            <tr>
                                <td style="padding: 10px; text-align: right;">Tạm tính:</td>
                                <td style="padding: 10px; text-align: right;">' . number_format($order->subtotal, 0, ',', '.') . 'đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; text-align: right;">Phí vận chuyển:</td>
                                <td style="padding: 10px; text-align: right;">' . number_format($order->shipping_fee, 0, ',', '.') . 'đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; text-align: right;">Khuyến mãi:</td>
                                <td style="padding: 10px; text-align: right; color: green;">-' . number_format($order->discount_amount, 0, ',', '.') . 'đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px;">TỔNG CỘNG:</td>
                                <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px; color: #e53e3e;">' . number_format($order->grand_total, 0, ',', '.') . 'đ</td>
                            </tr>
                        </table>

                        <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 5px; color: #333;">Thông tin giao hàng</h3>
                        <p><strong>Người nhận:</strong> ' . htmlspecialchars($order->recipient_name) . '</p>
                        <p><strong>Điện thoại:</strong> ' . htmlspecialchars($order->recipient_phone) . '</p>
                        <p><strong>Địa chỉ:</strong> ' . htmlspecialchars($order->shipping_address) . '</p>
                        <p><strong>Phương thức TT:</strong> ' . strtoupper($order->payment_method) . '</p>

                        <div style="text-align: center; margin-top: 30px;">
                            <a href="http://localhost:3302/profile/orders" style="background: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Xem lịch sử đơn hàng</a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ';

            $emailMessage = (new \Symfony\Component\Mime\Email())
                ->from($emailUser)
                ->to($user->email)
                ->subject('📦 Xác nhận đơn hàng đặt thành công ' . $order->order_code)
                ->html($htmlBody);

            $mailer->send($emailMessage);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send order email: " . $e->getMessage());
            return false;
        }
    }
}
