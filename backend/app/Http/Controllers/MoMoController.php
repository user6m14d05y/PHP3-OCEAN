<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;

class MoMoController extends Controller
{
    private $partnerCode;
    private $accessKey;
    private $secretKey;
    private $endpoint;
    private $redirectUrl;
    private $ipnUrl;

    public function __construct()
    {
        $this->partnerCode = config('momo.partner_code');
        $this->accessKey   = config('momo.access_key');
        $this->secretKey   = config('momo.secret_key');
        $this->endpoint    = config('momo.endpoint');
        $this->redirectUrl = config('momo.redirect_url');
        $this->ipnUrl      = config('momo.ipn_url');
    }

    /**
     * Xử lý URL Return khi người dùng thanh toán xong và được redirect về Website
     */
    public function momoReturn(Request $request)
    {
        // Kiểm tra mã đơn hàng
        if (!$request->has('orderId')) {
            return response()->json([
                "status" => "error",
                "message" => "Thiếu mã đơn hàng (orderId)"
            ], 400);
        }

        // Xác thực chữ ký
        if (!$this->verifySignature($request->all())) {
            return response()->json([
                "status" => "error",
                "message" => "Chữ ký trả về không hợp lệ"
            ], 400);
        }

        $resultCode = $request->input('resultCode');
        $orderCode = $request->input('orderId');

        $order = \App\Models\Order::where('order_code', $orderCode)->first();

        if ($resultCode == "0") {
            if ($order && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid']);
                \App\Models\Payment::updateOrCreate(
                    ['order_id' => $order->order_id, 'payment_method' => 'momo'],
                    [
                        'transaction_code' => $request->input('transId'),
                        'amount' => $request->input('amount'),
                        'status' => 'success',
                        'paid_at' => now(),
                        'gateway_response' => json_encode($request->all())
                    ]
                );
                
                \App\Models\OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'new_status' => $order->fulfillment_status,
                    'note' => 'Thanh toán MoMo thành công',
                ]);
                
                $this->dispatchPostPaymentActions($order);
            }

            return response()->json([
                "status" => "success",
                "payment_status" => "paid",
                "message" => "Thanh toán thành công",
                "data" => [
                    "order_code" => $orderCode,
                    "grand_total" => $request->input('amount'),
                    "transaction_no" => $request->input('transId'),
                    "pay_date" => $request->input('responseTime'),
                ],
                "method" => "MOMO",
            ]);
        }
        
        if ($order && $order->payment_status !== 'paid') {
            $order->update(['payment_status' => 'failed']);
            \App\Models\Payment::updateOrCreate(
                ['order_id' => $order->order_id, 'payment_method' => 'momo'],
                [
                    'transaction_code' => $request->input('transId'),
                    'amount' => $request->input('amount'),
                    'status' => 'failed',
                    'gateway_response' => json_encode($request->all())
                ]
            );
        }

        return response()->json([
            "status" => "error",
            "payment_status" => "failed",
            "message" => "Thanh toán thất bại hoặc người dùng đã hủy giao dịch",
            "data" => [
                "order_code" => $orderCode,
                "grand_total" => $request->input('amount')
            ],
            "method" => "MOMO",
        ]);
    }



    /**
     * Tích hợp URL IPN (Webhook) để tự động cập nhật trạng thái đơn hàng ngầm phía Server
     */
    public function momoIpn(Request $request)
    {
        $data = $request->all();

        if (empty($data)) {
            return response()->json(["message" => "No data received"], 400);
        }

        // Xác thực chữ ký của MoMo gọi qua Server
        if (!$this->verifySignature($data)) {
            Log::error('MoMo IPN - Invalid Signature', $data);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature'
            ], 400);
        }

        $resultCode = $request->input('resultCode');
        $orderCode = $request->input('orderId');

        try {
            $order = \App\Models\Order::where('order_code', $orderCode)->first();
            
            if (!$order) {
                Log::warning("MoMo IPN: Không tìm thấy hóa đơn $orderCode");
                return response()->json(['message' => 'Order not found'], 404);
            }

            if ($resultCode == 0) {
                if ($order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid']);
                    \App\Models\Payment::updateOrCreate(
                        ['order_id' => $order->order_id, 'payment_method' => 'momo'],
                        [
                            'transaction_code' => $request->input('transId'),
                            'amount' => $request->input('amount'),
                            'status' => 'success',
                            'paid_at' => now(),
                            'gateway_response' => json_encode($request->all())
                        ]
                    );
                                        
                    \App\Models\OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'new_status' => $order->fulfillment_status,
                        'note' => 'Thanh toán MoMo thành công',
                    ]);
                    
                    Log::info("Thanh toán MoMo thành công đơn hàng số: $orderCode");
                    
                    $this->dispatchPostPaymentActions($order);
                }
            } else {
                // Xử lý logic cho việc thanh toán thất bại
                Log::warning("Thanh toán MoMo thất bại đơn hàng số: $orderCode");
                \App\Models\Payment::updateOrCreate(
                    ['order_id' => $order->order_id, 'payment_method' => 'momo'],
                    [
                        'transaction_code' => $request->input('transId'),
                        'amount' => $request->input('amount'),
                        'status' => 'failed',
                        'gateway_response' => json_encode($request->all())
                    ]
                );
            }
            
            // MoMo yêu cầu status code 204 No Content khi xử lý đúng đắn IPN
            return response()->noContent();
            
        } catch (\Exception $e) {
            Log::error('MoMo IPN Handling Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Hàm dùng chung để kiểm tra Signature hợp lệ (dùng chuẩn MoMo API v2)
     */
    private function verifySignature(array $data)
    {
        $momoSignature = $data['signature'] ?? '';

        // Dựa vào tài liệu MoMo v2, thứ tự tạo chữ ký so sánh từ Dữ liệu MoMo gửi
        $rawHash = "accessKey=" . $this->accessKey .
            "&amount=" . ($data['amount'] ?? '') .
            "&extraData=" . ($data['extraData'] ?? '') .
            "&message=" . ($data['message'] ?? '') .
            "&orderId=" . ($data['orderId'] ?? '') .
            "&orderInfo=" . ($data['orderInfo'] ?? '') .
            "&orderType=" . ($data['orderType'] ?? '') .
            "&partnerCode=" . ($data['partnerCode'] ?? '') .
            "&payType=" . ($data['payType'] ?? '') .
            "&requestId=" . ($data['requestId'] ?? '') .
            "&responseTime=" . ($data['responseTime'] ?? '') .
            "&resultCode=" . ($data['resultCode'] ?? '') .
            "&transId=" . ($data['transId'] ?? '');

        // Tính toán chữ ký HMAC SHA256 để so sánh
        $expectedSignature = hash_hmac('sha256', $rawHash, $this->secretKey);

        return hash_equals($expectedSignature, $momoSignature);
    }

    /**
     * Dispatch email xác nhận + realtime event + xóa cart items sau khi thanh toán thành công.
     */
    private function dispatchPostPaymentActions(Order $order): void
    {
        // Xóa cart items của user
        try {
            $cart = Cart::where('user_id', $order->user_id)->where('status', 'active')->first();
            if ($cart) {
                $orderVariantIds = $order->items()->pluck('variant_id')->toArray();
                CartItem::where('cart_id', $cart->cart_id)
                    ->whereIn('variant_id', $orderVariantIds)
                    ->where('selected', true)
                    ->delete();
            }
        } catch (\Exception $e) {
            Log::error('MoMo post-payment: Cart cleanup failed', ['error' => $e->getMessage()]);
        }

        // Gửi realtime event cho admin dashboard
        try {
            event(new \App\Events\OrderCreatedAdmin($order));
        } catch (\Exception $e) {
            Log::error('MoMo post-payment: Realtime event failed', ['error' => $e->getMessage()]);
        }

        // Gửi email xác nhận đơn hàng
        try {
            $this->sendPaymentConfirmationEmail($order);
            
            // ★ FIX: Đánh dấu email_sent = true để cron job SendOrderEmails
            // KHÔNG gửi thêm email "Đặt hàng thành công" (trùng lặp)
            $order->update(['email_sent' => true]);
            
            $notificationData = [
                'title'       => '✅ Thanh toán thành công',
                'message'     => 'Đơn hàng ' . $order->order_code . ' đã được thanh toán thành công qua MoMo.',
                'order_code'  => $order->order_code,
                'grand_total' => $order->grand_total,
                'type'        => 'payment_success'
            ];
            
            DB::table('notifications')->insert([
                'id'              => \Illuminate\Support\Str::uuid(),
                'type'            => 'App\Notifications\OrderPaidNotification',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $order->user_id,
                'data'            => json_encode($notificationData),
                'read_at'         => null,
                'created_at'      => \Carbon\Carbon::now(),
                'updated_at'      => \Carbon\Carbon::now(),
            ]);

            event(new \App\Events\UserNotificationEvent($order->user_id, $notificationData));
        } catch (\Exception $e) {
            Log::error('MoMo post-payment: Email/Notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Gửi email xác nhận thanh toán MoMo thành công
     */
    private function sendPaymentConfirmationEmail(Order $order): void
    {
        $order->load(['items', 'user']);
        $user = $order->user;

        if (!$user || empty($user->email)) {
            return;
        }

        $emailUser = env('EMAIL_USER', env('MAIL_USERNAME'));
        $emailPass = env('EMAIL_PASS', env('MAIL_PASSWORD'));

        if (!$emailUser || !$emailPass) {
            Log::warning('Skip sending MoMo confirmation email: mail credentials missing.');
            return;
        }

        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            'smtp.gmail.com',
            587,
            false
        );
        $transport->setUsername($emailUser);
        $transport->setPassword($emailPass);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $itemsHtml = '';
        foreach ($order->items as $item) {
            $variantInfo = $item->variant_name ? '(' . $item->color . '/' . $item->size . ')' : '';
            $itemsHtml .= '
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item->product_name) . ' ' . $variantInfo . ' x' . $item->quantity . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">' . number_format($item->line_total, 0, ',', '.') . 'đ</td>
            </tr>';
        }

        $frontendUrl = env('FRONTEND_URL', 'https://ocean.pro.vn');

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="background: #a50064; padding: 20px; text-align: center; color: white;">
                    <h2 style="margin: 0;">Thanh toán thành công!</h2>
                    <p style="margin: 5px 0 0;">Đơn hàng ' . $order->order_code . ' đã được xác nhận thanh toán qua ví MoMo</p>
                </div>
                <div style="padding: 20px;">
                    <p>Xin chào <strong>' . htmlspecialchars($order->recipient_name) . '</strong>,</p>
                    <p>Chúng tôi xác nhận đơn hàng <strong>' . $order->order_code . '</strong> đã được thanh toán thành công vào lúc ' . now()->format('H:i d/m/Y') . '.</p>
                    
                    <h3 style="border-bottom: 2px solid #a50064; padding-bottom: 5px; color: #333;">Chi tiết đơn hàng</h3>
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

                    <div style="background: #fdf2f8; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #831843;"><strong>Phương thức thanh toán:</strong> Ví MoMo (Đã thanh toán)</p>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="' . $frontendUrl . '/profile/orders" style="background: #a50064; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Xem lịch sử đơn hàng</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';

        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from($emailUser)
            ->to($user->email)
            ->subject('✅ Thanh toán thành công — Đơn hàng ' . $order->order_code)
            ->html($htmlBody);

        $mailer->send($emailMessage);

        Log::info('MoMo confirmation email sent', [
            'order_code' => $order->order_code,
            'to' => $user->email,
        ]);
    }
}
