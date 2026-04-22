<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    /**
     * Lấy danh sách đơn hàng cho Admin
     */
    public function index(Request $request)
    {
        $query = Order::with(['items', 'user'])->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('fulfillment_status', $request->status);
        }

        if ($request->has('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('order_code', 'like', "%{$searchTerm}%")
                  ->orWhere('recipient_name', 'like', "%{$searchTerm}%")
                  ->orWhere('recipient_phone', 'like', "%{$searchTerm}%");
            });
        }

        // --- BỔ SUNG LỌC THEO NGÀY ĐẶT HÀNG ---
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    /**
     * Lấy chi tiết đơn hàng
     */
    public function show($id)
    {
        $order = Order::with(['items.product', 'items.variant', 'user', 'statusHistories'])->where('order_id', $id)->first();

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    /**
     * Cập nhật trạng thái đơn hàng (Fulfillment Status & Payment Status)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'fulfillment_status' => 'nullable|in:pending,confirmed,packing,shipping,delivered,completed,cancelled,returned',
            'note' => 'nullable|string'
        ]);

        $order = Order::where('order_id', $id)->first();
        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        // Luồng trạng thái tuần tự - không cho nhảy cóc
        $allowedTransitions = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['packing', 'cancelled'],
            'packing'   => ['shipping', 'cancelled'],
            'shipping'  => ['delivered'],
            'delivered' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        DB::beginTransaction();
        try {
            $oldFulfillmentStatus = $order->fulfillment_status;
            $oldPaymentStatus = $order->payment_status;

            $updates = [];

            if ($request->has('fulfillment_status')) {
                // Nếu chọn đúng trạng thái hiện tại → báo lỗi
                if ($request->fulfillment_status === $order->fulfillment_status) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Đơn hàng đang ở trạng thái '{$order->fulfillment_status}' rồi. Vui lòng chọn trạng thái tiếp theo!"
                    ], 422);
                }
                // Kiểm tra luồng trạng thái hợp lệ
                $allowed = $allowedTransitions[$order->fulfillment_status] ?? [];
                if (!in_array($request->fulfillment_status, $allowed)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Không thể chuyển từ '{$order->fulfillment_status}' sang '{$request->fulfillment_status}'. Vui lòng thực hiện theo đúng quy trình!"
                    ], 422);
                }

                $updates['fulfillment_status'] = $request->fulfillment_status;

                // Tự động set thời gian
                $statusFieldMap = [
                    'confirmed' => 'confirmed_at',
                    'shipping' => 'shipped_at',
                    'delivered' => 'delivered_at',
                    'completed' => 'completed_at',
                    'cancelled' => 'cancelled_at'
                ];

                if (isset($statusFieldMap[$request->fulfillment_status])) {
                    $updates[$statusFieldMap[$request->fulfillment_status]] = now();
                }

                // ===== TỰ ĐỘNG CẬP NHẬT PAYMENT STATUS =====
                // Khi đơn hoàn thành + COD + chưa thanh toán → tự động chuyển "paid"
                if ($request->fulfillment_status === 'completed' && $order->payment_method === 'cod' && $order->payment_status === 'unpaid') {
                    $updates['payment_status'] = 'paid';
                }

                if ($request->fulfillment_status === 'cancelled') {
                    $updates['cancel_reason'] = $request->note ?? 'Hủy bởi Admin';

                    // Nếu đơn đã thanh toán online (vnpay/momo) → tự động hoàn tiền
                    if (in_array($order->payment_method, ['vnpay', 'momo', 'bank_transfer']) && $order->payment_status === 'paid') {
                        $updates['payment_status'] = 'refunded';
                    }

                    // Hoàn lại tồn kho bằng 1 query duy nhất để tránh N+1 update
                    $cases = [];
                    $bindings = [];
                    $variantIds = [];

                    foreach ($order->items as $item) {
                        $cases[] = "WHEN ? THEN stock + ?";
                        $bindings[] = $item->variant_id;
                        $bindings[] = $item->quantity;
                        $variantIds[] = $item->variant_id;
                    }

                    if (!empty($variantIds)) {
                        $ids = implode(',', array_fill(0, count($variantIds), '?'));
                        $casesSql = implode(' ', $cases);
                        // Thêm binding cho mệnh đề IN
                        $bindings = array_merge($bindings, $variantIds);
                        DB::statement("UPDATE product_variants SET stock = CASE variant_id {$casesSql} END, updated_at = NOW() WHERE variant_id IN ({$ids})", $bindings);
                    }
                }
            }

            if (!empty($updates)) {
                $order->update($updates);

                // Lưu lịch sử nếu fulfillment status đổi
                if (isset($updates['fulfillment_status'])) {
                    OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'old_status' => $oldFulfillmentStatus,
                        'new_status' => $updates['fulfillment_status'],
                        'note' => $request->note ?? "Chuyển trạng thái bởi Admin",
                    ]);
                }

                // Lưu lịch sử nếu payment status tự động thay đổi
                if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                    OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'old_status' => $oldPaymentStatus,
                        'new_status' => $updates['payment_status'],
                        'note' => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
                    ]);
                }
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Cập nhật trạng thái thành công!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái đơn hàng lỗi: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Có lỗi xảy ra!'], 500);
        }
    }

    /**
     * Cập nhật trạng thái hàng loạt
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,order_id',
            'fulfillment_status' => 'nullable|in:pending,confirmed,packing,shipping,delivered,completed,cancelled,returned',
            'note' => 'nullable|string'
        ]);

        $orderIds = $request->order_ids;
        $orders = Order::whereIn('order_id', $orderIds)->get();

        if ($orders->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng nào để cập nhật!'], 404);
        }

        $allowedTransitions = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['packing', 'cancelled'],
            'packing'   => ['shipping', 'cancelled'],
            'shipping'  => ['delivered'],
            'delivered' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];

        // Mảng chứa các đơn hàng vi phạm
        $invalidOrders = [];

        // 1. Kiểm tra toàn bộ lô đơn xem có hợp lệ không
        foreach ($orders as $order) {
            if ($request->filled('fulfillment_status') && $request->fulfillment_status !== $order->fulfillment_status) {
                $allowed = $allowedTransitions[$order->fulfillment_status] ?? [];
                if (!in_array($request->fulfillment_status, $allowed)) {
                    $invalidOrders[] = "#{$order->order_code} (Chuyển Giao hàng không hợp lệ)";
                    continue;
                }
            }
        }

        // Báo lỗi toàn bộ lô nếu có 1 đơn không hợp lệ
        if (!empty($invalidOrders)) {
            $invalidList = implode(', ', $invalidOrders);
            return response()->json([
                'status' => 'error',
                'message' => "Hủy thao tác do có đơn hàng không hợp lệ: {$invalidList}. Vui lòng bỏ chọn các đơn này và thử lại!"
            ], 422);
        }

        // 2. Thực thi cập nhật thực tế
        DB::beginTransaction();
        try {
            $updatedCount = 0;

            foreach ($orders as $order) {
                $oldFulfillmentStatus = $order->fulfillment_status;
                $oldPaymentStatus = $order->payment_status;
                $updates = [];

                if ($request->filled('fulfillment_status') && $request->fulfillment_status !== $order->fulfillment_status) {
                    $updates['fulfillment_status'] = $request->fulfillment_status;

                    $statusFieldMap = [
                        'confirmed' => 'confirmed_at',
                        'shipping' => 'shipped_at',
                        'delivered' => 'delivered_at',
                        'completed' => 'completed_at',
                        'cancelled' => 'cancelled_at'
                    ];

                    if (isset($statusFieldMap[$request->fulfillment_status])) {
                        $updates[$statusFieldMap[$request->fulfillment_status]] = now();
                    }

                    // ===== TỰ ĐỘNG CẬP NHẬT PAYMENT STATUS =====
                    // Khi đơn hoàn thành + COD + chưa thanh toán → tự động chuyển "paid"
                    if ($request->fulfillment_status === 'completed' && $order->payment_method === 'cod' && $order->payment_status === 'unpaid') {
                        $updates['payment_status'] = 'paid';
                    }

                    if ($request->fulfillment_status === 'cancelled') {
                        $updates['cancel_reason'] = $request->note ?? 'Hủy hàng loạt bởi Admin';

                        // Nếu đơn đã thanh toán online (vnpay/momo) → tự động hoàn tiền
                        if (in_array($order->payment_method, ['vnpay', 'momo', 'bank_transfer']) && $order->payment_status === 'paid') {
                            $updates['payment_status'] = 'refunded';
                        }

                        // Hoàn tồn kho
                        $cases = [];
                        $bindings = [];
                        $variantIds = [];

                        $items = DB::table('order_items')->where('order_id', $order->order_id)->get();
                        foreach ($items as $item) {
                            if ($item->variant_id) {
                                $cases[] = "WHEN ? THEN stock + ?";
                                $bindings[] = $item->variant_id;
                                $bindings[] = $item->quantity;
                                $variantIds[] = $item->variant_id;
                            }
                        }

                        if (!empty($variantIds)) {
                            $ids = implode(',', array_fill(0, count($variantIds), '?'));
                            $casesSql = implode(' ', $cases);
                            $bindings = array_merge($bindings, $variantIds);
                            DB::statement("UPDATE product_variants SET stock = CASE variant_id {$casesSql} END, updated_at = NOW() WHERE variant_id IN ({$ids})", $bindings);
                        }
                    }
                }

                if (!empty($updates)) {
                    $order->update($updates);
                    $updatedCount++;

                    if (isset($updates['fulfillment_status'])) {
                        OrderStatusHistory::create([
                            'order_id' => $order->order_id,
                            'old_status' => $oldFulfillmentStatus,
                            'new_status' => $updates['fulfillment_status'],
                            'note' => $request->note ?? "Chuyển trạng thái hàng loạt bởi Admin",
                        ]);
                    }

                    // Lưu lịch sử nếu payment status tự động thay đổi
                    if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                        OrderStatusHistory::create([
                            'order_id' => $order->order_id,
                            'old_status' => $oldPaymentStatus,
                            'new_status' => $updates['payment_status'],
                            'note' => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
                        ]);
                    }
                }
            }

            DB::commit();

            if ($updatedCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tất cả đơn hàng đã ở trạng thái được chọn rồi. Không có gì thay đổi!'
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật trạng thái hàng loạt thành công cho ' . $updatedCount . ' đơn hàng!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái hàng loạt lỗi: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Có lỗi hệ thống xảy ra!'], 500);
        }
    }

    /**
     * Đồng bộ đơn hàng lên GHN
     */
    public function syncGHN($id)
    {
        $order = Order::with(['items', 'address'])->where('order_id', $id)->first();
        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        try {
            $result = \App\Services\GHNService::createOrder($order);
            
            // Optionally, save the GHN order code to your database here
            // if you add a 'shipping_code' column to the orders table.
            // $order->update(['shipping_code' => $result['data']['order_code']]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Đã tạo đơn hàng trên GHN thành công!',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
