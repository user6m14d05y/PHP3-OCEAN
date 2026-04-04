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
            'payment_status' => 'nullable|in:unpaid,paid,failed,refunded,partially_refunded',
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
            
            $updates = [];
            
            if ($request->has('fulfillment_status') && $request->fulfillment_status !== $order->fulfillment_status) {
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
                
                if ($request->fulfillment_status === 'cancelled') {
                    $updates['cancel_reason'] = $request->note ?? 'Hủy bởi Admin';
                    // Hoàn lại tồn kho
                    foreach ($order->items as $item) {
                        ProductVariant::where('variant_id', $item->variant_id)->increment('stock', $item->quantity);
                    }
                }
            }

            if ($request->has('payment_status')) {
                $updates['payment_status'] = $request->payment_status;
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
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Cập nhật trạng thái thành công!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái đơn hàng lỗi: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Có lỗi xảy ra!'], 500);
        }
    }
}
