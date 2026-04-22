<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\VNPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    /**
     * Xử lý kết quả thanh toán VNPay trả về (Return URL)
     * 
     * Flow:
     * 1. VNPay redirect user về URL này kèm query params
     * 2. Verify checksum → tìm order → cập nhật payment_status
     * 3. Trả JSON cho frontend render kết quả
     * 
     * Route: GET /api/payment/vnpay-return (public, không cần auth)
     */
    public function vnpayReturn(Request $request)
    {
        try {
            $queryParams = $request->all();

            // Verify chữ ký HMAC-SHA512 từ VNPay
            $result = VNPayService::verifyReturn($queryParams);

            if (!$result['isValid']) {
                Log::warning('VNPay Return: Invalid secure hash', [
                    'params' => $queryParams,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chữ ký không hợp lệ. Giao dịch có thể bị giả mạo.',
                    'payment_status' => 'failed',
                ], 400);
            }

            // Tìm order theo vnp_TxnRef (= order_code) — dùng lockForUpdate chống race condition
            $order = DB::transaction(function () use ($result, $queryParams, $request) {
                $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

                if (!$order) {
                    return null;
                }

                // Tránh xử lý trùng lặp (idempotency)
                if ($order->payment_status === 'paid') {
                    return $order; // Return early, sẽ check bên ngoài
                }

                // vnp_ResponseCode = "00" nghĩa là giao dịch thành công
                if ($result['responseCode'] === '00') {
                    // Verify số tiền khớp với đơn hàng (bảo mật thêm 1 lớp)
                    if (abs($result['amount'] - $order->grand_total) > 1) {
                        Log::error('VNPay Return: Amount mismatch', [
                            'vnpay_amount' => $result['amount'],
                            'order_total' => $order->grand_total,
                            'ip' => $request->ip(),
                        ]);
                        return 'amount_mismatch';
                    }

                    // Cập nhật order payment_status = paid
                    $order->update([
                        'payment_status' => 'paid',
                    ]);

                    // Cập nhật hoặc tạo Payment record
                    // Không json_encode — Model đã cast 'gateway_response' => 'array'
                    Payment::updateOrCreate(
                        ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
                        [
                            'transaction_code' => $result['transactionNo'],
                            'amount' => $result['amount'],
                            'status' => 'success',
                            'paid_at' => now(),
                            'gateway_response' => $queryParams,
                        ]
                    );

                    Log::info('VNPay Return: Payment success', [
                        'order_code' => $order->order_code,
                        'transaction_no' => $result['transactionNo'],
                        'ip' => $request->ip(),
                    ]);
                } else {
                    // Giao dịch thất bại hoặc bị hủy
                    $order->update([
                        'payment_status' => 'failed',
                    ]);

                    Payment::updateOrCreate(
                        ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
                        [
                            'transaction_code' => $result['transactionNo'],
                            'amount' => $result['amount'],
                            'status' => 'failed',
                            'gateway_response' => $queryParams,
                        ]
                    );

                    Log::info('VNPay Return: Payment failed', [
                        'order_code' => $order->order_code,
                        'response_code' => $result['responseCode'],
                        'ip' => $request->ip(),
                    ]);
                }

                return $order;
            });

            // --- Xử lý kết quả bên ngoài transaction ---

            if ($order === null) {
                Log::error('VNPay Return: Order not found', ['txnRef' => $result['txnRef']]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy đơn hàng.',
                    'payment_status' => 'failed',
                ], 404);
            }

            if ($order === 'amount_mismatch') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Số tiền thanh toán không khớp với đơn hàng.',
                    'payment_status' => 'failed',
                ], 400);
            }

            // Idempotency: nếu đã paid từ trước (ví dụ IPN xử lý trước)
            if ($order->payment_status === 'paid' && $result['responseCode'] === '00') {
                // Dispatch email + event nếu chưa (safe vì idempotent)
                $this->dispatchPostPaymentActions($order);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Thanh toán thành công!',
                    'payment_status' => 'paid',
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                        'transaction_no' => $result['transactionNo'],
                        'bank_code' => $result['bankCode'],
                        'pay_date' => $result['payDate'],
                    ]
                ]);
            }

            // Payment thành công — dispatch email + realtime event
            if ($result['responseCode'] === '00') {
                $this->dispatchPostPaymentActions($order);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Thanh toán thành công!',
                    'payment_status' => 'paid',
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                        'transaction_no' => $result['transactionNo'],
                        'bank_code' => $result['bankCode'],
                        'pay_date' => $result['payDate'],
                    ]
                ]);
            }

            // Payment thất bại
            $errorMessage = self::getResponseMessage($result['responseCode']);

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'payment_status' => 'failed',
                'data' => [
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total,
                    'response_code' => $result['responseCode'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('VNPay return error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi khi xử lý thanh toán.',
                'payment_status' => 'failed',
            ], 500);
        }
    }

    /**
     * VNPay IPN (Instant Payment Notification) — Server-to-server callback
     * 
     * Đây là endpoint quan trọng nhất:
     * - VNPay gọi trực tiếp từ server, không qua trình duyệt user
     * - Đảm bảo payment luôn được xác nhận ngay cả khi user đóng browser
     * - Response phải đúng format VNPay yêu cầu: { RspCode, Message }
     * 
     * Route: POST /api/payment/vnpay-ipn (public)
     */
    public function vnpayIpn(Request $request)
    {
        try {
            $queryParams = $request->all();

            Log::info('VNPay IPN received', [
                'txnRef' => $queryParams['vnp_TxnRef'] ?? 'N/A',
                'ip' => $request->ip(),
            ]);

            // Verify chữ ký
            $result = VNPayService::verifyReturn($queryParams);

            if (!$result['isValid']) {
                Log::warning('VNPay IPN: Invalid checksum', [
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'RspCode' => '97',
                    'Message' => 'Invalid Checksum',
                ]);
            }

            // Tìm order — dùng lockForUpdate trong transaction để chống race condition
            $rspCode = '00';
            $rspMessage = 'Confirm Success';

            DB::transaction(function () use ($result, $queryParams, $request, &$rspCode, &$rspMessage) {
                $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

                if (!$order) {
                    $rspCode = '01';
                    $rspMessage = 'Order not Found';
                    return;
                }

                // Nếu đã xử lý rồi → trả success (idempotent)
                if ($order->payment_status === 'paid') {
                    $rspCode = '02';
                    $rspMessage = 'Order already confirmed';
                    return;
                }

                // Verify số tiền
                if (abs($result['amount'] - $order->grand_total) > 1) {
                    Log::error('VNPay IPN: Amount mismatch', [
                        'vnpay_amount' => $result['amount'],
                        'order_total' => $order->grand_total,
                        'ip' => $request->ip(),
                    ]);
                    $rspCode = '04';
                    $rspMessage = 'Invalid Amount';
                    return;
                }

                if ($result['responseCode'] === '00') {
                    // Thanh toán thành công
                    $order->update(['payment_status' => 'paid']);

                    Payment::updateOrCreate(
                        ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
                        [
                            'transaction_code' => $result['transactionNo'],
                            'amount' => $result['amount'],
                            'status' => 'success',
                            'paid_at' => now(),
                            'gateway_response' => $queryParams,
                        ]
                    );

                    Log::info('VNPay IPN: Payment confirmed', [
                        'order_code' => $order->order_code,
                        'transaction_no' => $result['transactionNo'],
                        'ip' => $request->ip(),
                    ]);

                    // Dispatch email + realtime event (bên ngoài transaction sẽ tốt hơn 
                    // nhưng ở đây ta cần order reference)
                    $this->dispatchPostPaymentActions($order);
                } else {
                    // Thanh toán thất bại
                    $order->update(['payment_status' => 'failed']);

                    Payment::updateOrCreate(
                        ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
                        [
                            'transaction_code' => $result['transactionNo'],
                            'amount' => $result['amount'],
                            'status' => 'failed',
                            'gateway_response' => $queryParams,
                        ]
                    );

                    Log::info('VNPay IPN: Payment failed', [
                        'order_code' => $order->order_code,
                        'response_code' => $result['responseCode'],
                    ]);
                }
            });

            return response()->json([
                'RspCode' => $rspCode,
                'Message' => $rspMessage,
            ]);
        } catch (\Exception $e) {
            Log::error('VNPay IPN error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'RspCode' => '99',
                'Message' => 'Unknown error',
            ]);
        }
    }

    /**
     * Dispatch email xác nhận + realtime event + xóa cart items sau khi thanh toán thành công.
     * Method này idempotent — gọi nhiều lần không gây side effect nghiêm trọng.
     */
    private function dispatchPostPaymentActions(Order $order): void
    {
        // Xóa cart items của user (giỏ hàng đã được giữ lại khi tạo đơn VNPay/MoMo)
        try {
            $cart = Cart::where('user_id', $order->user_id)->where('status', 'active')->first();
            if ($cart) {
                // Lấy variant_id từ order items để xóa đúng cart items tương ứng
                $orderVariantIds = $order->items()->pluck('variant_id')->toArray();
                CartItem::where('cart_id', $cart->cart_id)
                    ->whereIn('variant_id', $orderVariantIds)
                    ->where('selected', true)
                    ->delete();

                Log::info('VNPay post-payment: Cart items cleared', [
                    'order_code' => $order->order_code,
                    'user_id' => $order->user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Cart cleanup failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);
        }

        // Gửi realtime event cho admin dashboard
        try {
            event(new \App\Events\OrderCreatedAdmin($order));
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Realtime event failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);
        }

        // Gửi email xác nhận đơn hàng
        try {
            $this->sendPaymentConfirmationEmail($order);
            
            // --- Ghi log notification cho khách hàng ---
            $notificationData = [
                'title'       => '✅ Thanh toán thành công',
                'message'     => 'Đơn hàng ' . $order->order_code . ' đã được thanh toán thành công qua VNPay.',
                'order_code'  => $order->order_code,
                'grand_total' => $order->grand_total,
                'type'        => 'payment_success'
            ];
            
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => \Illuminate\Support\Str::uuid(),
                'type'            => 'App\Notifications\OrderPaidNotification',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $order->user_id,
                'data'            => json_encode($notificationData),
                'read_at'         => null,
                'created_at'      => \Carbon\Carbon::now(),
                'updated_at'      => \Carbon\Carbon::now(),
            ]);

            // Broadcast realtime event
            event(new \App\Events\UserNotificationEvent($order->user_id, $notificationData));
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Email/Notification failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gửi email xác nhận thanh toán VNPay thành công
     */
    private function sendPaymentConfirmationEmail(Order $order): void
    {
        $order->load(['items', 'user']);
        $user = $order->user;

        if (!$user || empty($user->email)) {
            return;
        }

        $emailUser = config('mail.mailers.smtp.username');
        $emailPass = config('mail.mailers.smtp.password');

        // Fallback: thử config custom nếu Laravel mail chưa cấu hình
        if (!$emailUser) {
            $emailUser = config('services.email.username');
            $emailPass = config('services.email.password');
        }

        if (!$emailUser || !$emailPass) {
            Log::warning('Skip sending VNPay confirmation email: mail credentials missing.');
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

        $frontendUrl = config('app.frontend_url', 'http://localhost:3302');

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="background: #16a34a; padding: 20px; text-align: center; color: white;">
                    <h2 style="margin: 0;">Thanh toán thành công!</h2>
                    <p style="margin: 5px 0 0;">Đơn hàng ' . $order->order_code . ' đã được xác nhận thanh toán qua VNPay</p>
                </div>
                <div style="padding: 20px;">
                    <p>Xin chào <strong>' . htmlspecialchars($order->recipient_name) . '</strong>,</p>
                    <p>Chúng tôi xác nhận đơn hàng <strong>' . $order->order_code . '</strong> đã được thanh toán thành công vào lúc ' . now()->format('H:i d/m/Y') . '.</p>
                    
                    <h3 style="border-bottom: 2px solid #16a34a; padding-bottom: 5px; color: #333;">Chi tiết đơn hàng</h3>
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

                    <div style="background: #dcfce7; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #166534;"><strong>Phương thức thanh toán:</strong> VNPay (Đã thanh toán)</p>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="' . $frontendUrl . '/profile/orders" style="background: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Xem lịch sử đơn hàng</a>
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

        Log::info('VNPay confirmation email sent', [
            'order_code' => $order->order_code,
            'to' => $user->email,
        ]);
    }

    /**
     * Mapping VNPay response codes sang thông báo tiếng Việt
     */
    private static function getResponseMessage(string $code): string
    {
        $messages = [
            '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
            '09' => 'Giao dịch không thành công: Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
            '10' => 'Giao dịch không thành công: Xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
            '11' => 'Giao dịch không thành công: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại.',
            '12' => 'Giao dịch không thành công: Thẻ/Tài khoản bị khóa.',
            '13' => 'Giao dịch không thành công: Nhập sai mật khẩu xác thực (OTP). Xin quý khách vui lòng thực hiện lại.',
            '24' => 'Giao dịch không thành công: Khách hàng hủy giao dịch.',
            '51' => 'Giao dịch không thành công: Tài khoản không đủ số dư để thực hiện giao dịch.',
            '65' => 'Giao dịch không thành công: Tài khoản đã vượt quá hạn mức giao dịch trong ngày.',
            '75' => 'Ngân hàng thanh toán đang bảo trì.',
            '79' => 'Giao dịch không thành công: Nhập sai mật khẩu thanh toán quá số lần quy định.',
            '99' => 'Có lỗi xảy ra trong quá trình thanh toán.',
        ];

        return $messages[$code] ?? 'Giao dịch không thành công. Mã lỗi: ' . $code;
    }
}
