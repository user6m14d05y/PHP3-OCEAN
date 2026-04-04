<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
                    \App\Models\Payment::where('order_id', $order->order_id)
                                        ->where('payment_method', 'momo')
                                        ->update(['status' => 'completed']);
                                        
                    \App\Models\OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'new_status' => $order->fulfillment_status,
                        'note' => 'Thanh toán MoMo thành công',
                    ]);
                    
                    Log::info("Thanh toán MoMo thành công đơn hàng số: $orderCode");
                }
            } else {
                // Xử lý logic cho việc thanh toán thất bại
                Log::warning("Thanh toán MoMo thất bại đơn hàng số: $orderCode");
                \App\Models\Payment::where('order_id', $order->order_id)
                                    ->where('payment_method', 'momo')
                                    ->where('status', 'pending')
                                    ->update(['status' => 'failed']);
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
}
