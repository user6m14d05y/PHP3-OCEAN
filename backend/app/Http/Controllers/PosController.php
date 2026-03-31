<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    /**
     * Quét barcode sản phẩm
     */
    public function scanProduct(Request $request)
    {
        $barcode = $request->input('code', '');

        if (empty($barcode)) {
            return response()->json(['status' => 'error', 'message' => 'Mã barcode không được để trống'], 422);
        }

        $variant = ProductVariant::with(['product.images', 'product.category'])
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (!$variant) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatVariantResponse($variant)
        ]);
    }

    /**
     * Tìm kiếm sản phẩm theo tên hoặc SKU
     */
    public function searchProducts(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 1) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $products = Product::with(['variants' => function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0);
            }, 'images', 'category'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('slug', 'LIKE', "%{$query}%")
                  ->orWhereHas('variants', function ($vq) use ($query) {
                      $vq->where('sku', 'LIKE', "%{$query}%")
                         ->orWhere('barcode', 'LIKE', "%{$query}%");
                  });
            })
            ->where('status', 'active')
            ->limit(20)
            ->get();

        $results = $products->map(function ($product) {
            $mainImage = $product->images->where('is_main', 1)->first();
            $thumbnail = $mainImage
                ? $mainImage->image_url
                : ($product->images->first()->image_url ?? $product->thumbnail_url);

            return [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'thumbnail' => $thumbnail,
                'category_name' => $product->category->name ?? '',
                'variants' => $product->variants->map(function ($v) {
                    return [
                        'variant_id' => $v->variant_id,
                        'variant_name' => $v->variant_name,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'color' => $v->color,
                        'size' => $v->size,
                        'price' => $v->price,
                        'compare_at_price' => $v->compare_at_price,
                        'stock' => $v->stock,
                        'image_url' => $v->image_url,
                    ];
                }),
            ];
        });

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    /**
     * Thanh toán POS - bán hàng trực tiếp
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'payment_method' => 'nullable|string|in:pos_cash,pos_transfer,pos_card',
            'note' => 'nullable|string|max:500',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        // Lấy admin/staff đang đăng nhập
        $staff = auth('admin')->user() ?? auth('api')->user();
        $staffId = $staff ? ($staff->admin_id ?? $staff->user_id) : null;

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $variant = ProductVariant::with('product')->where('variant_id', $item['variant_id'])->lockForUpdate()->first();

                if (!$variant || $variant->status !== 'active') {
                    $productName = $variant && $variant->product ? $variant->product->name : 'N/A';
                    throw new \Exception('Sản phẩm "' . $productName . '" không khả dụng.');
                }

                if ($variant->stock < $item['quantity']) {
                    throw new \Exception('Sản phẩm "' . $variant->product->name . '" (' . $variant->variant_name . ') chỉ còn ' . $variant->stock . ' trong kho.');
                }

                $lineTotal = $variant->price * $item['quantity'];
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'variant' => $variant,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = min($request->input('discount_amount', 0), $subtotal);
            $grandTotal = $subtotal - $discountAmount;

            // Tạo đơn hàng POS
            $order = Order::create([
                'order_code' => 'POS' . strtoupper(uniqid()) . rand(10, 99),
                'user_id' => $staffId,
                'recipient_name' => $request->input('customer_name', 'Khách lẻ'),
                'recipient_phone' => $request->input('customer_phone', ''),
                'shipping_address' => 'Mua tại cửa hàng',
                'note' => $request->input('note', ''),
                'payment_method' => $request->input('payment_method', 'pos_cash'),
                'payment_status' => 'paid',
                'fulfillment_status' => 'completed',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_fee' => 0,
                'grand_total' => $grandTotal,
                'completed_at' => now(),
            ]);

            // Tạo order items + trừ kho
            foreach ($itemsData as $data) {
                $v = $data['variant'];
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $v->product_id,
                    'variant_id' => $v->variant_id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->variant_name,
                    'sku' => $v->sku,
                    'color' => $v->color,
                    'size' => $v->size,
                    'quantity' => $data['quantity'],
                    'unit_price' => $v->price,
                    'line_total' => $data['line_total'],
                ]);

                $v->decrement('stock', $data['quantity']);
            }

            // Ghi lịch sử
            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'new_status' => 'completed',
                'note' => 'Bán hàng trực tiếp tại cửa hàng (POS)',
            ]);

            DB::commit();

            // Load lại order kèm items để trả về cho in hoá đơn
            $order->load('items');

            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán thành công!',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS Checkout failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format variant response cho scan
     */
    private function formatVariantResponse($variant)
    {
        $product = $variant->product;
        $mainImage = $product->images->where('is_main', 1)->first();

        return [
            'variant_id' => $variant->variant_id,
            'variant_name' => $variant->variant_name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => $variant->price,
            'stock' => $variant->stock,
            'image_url' => $variant->image_url,
            'product' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'thumbnail' => $mainImage->image_url ?? $product->thumbnail_url,
            ],
        ];
    }
}