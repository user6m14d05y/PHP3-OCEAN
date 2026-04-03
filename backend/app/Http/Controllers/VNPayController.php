<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
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
                Log::warning('VNPay: Invalid secure hash', ['params' => $queryParams]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chữ ký không hợp lệ. Giao dịch có thể bị giả mạo.',
                    'payment_status' => 'failed',
                ], 400);
            }

            // Tìm order theo vnp_TxnRef (= order_code)
            $order = Order::where('order_code', $result['txnRef'])->first();

            if (!$order) {
                Log::error('VNPay: Order not found', ['txnRef' => $result['txnRef']]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy đơn hàng.',
                    'payment_status' => 'failed',
                ], 404);
            }

            // Tránh xử lý trùng lặp (idempotency)
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Đơn hàng đã được thanh toán trước đó.',
                    'payment_status' => 'paid',
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                    ]
                ]);
            }

            DB::beginTransaction();

            // vnp_ResponseCode = "00" nghĩa là giao dịch thành công
            if ($result['responseCode'] === '00') {
                // Verify số tiền khớp với đơn hàng (bảo mật thêm 1 lớp)
                if (abs($result['amount'] - $order->grand_total) > 1) {
                    Log::error('VNPay: Amount mismatch', [
                        'vnpay_amount' => $result['amount'],
                        'order_total' => $order->grand_total,
                    ]);

                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Số tiền thanh toán không khớp với đơn hàng.',
                        'payment_status' => 'failed',
                    ], 400);
                }

                // Cập nhật order payment_status = paid
                $order->update([
                    'payment_status' => 'paid',
                ]);

                // Cập nhật hoặc tạo Payment record
                Payment::updateOrCreate(
                    ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
                    [
                        'transaction_code' => $result['transactionNo'],
                        'amount' => $result['amount'],
                        'status' => 'success',
                        'paid_at' => now(),
                        'gateway_response' => json_encode($queryParams),
                    ]
                );

                DB::commit();

                Log::info('VNPay: Payment success', [
                    'order_code' => $order->order_code,
                    'transaction_no' => $result['transactionNo'],
                ]);

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
                        'gateway_response' => json_encode($queryParams),
                    ]
                );

                DB::commit();

                $errorMessage = self::getResponseMessage($result['responseCode']);

                Log::info('VNPay: Payment failed', [
                    'order_code' => $order->order_code,
                    'response_code' => $result['responseCode'],
                ]);

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
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VNPay return error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi khi xử lý thanh toán.',
                'payment_status' => 'failed',
            ], 500);
        }
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
