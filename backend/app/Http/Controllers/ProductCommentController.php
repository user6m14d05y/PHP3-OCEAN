<?php

namespace App\Http\Controllers;

use App\Models\ProductComment;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductCommentController extends Controller
{
    /**
     * Store a newly created comment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:1000',
            'product_id' => 'required|exists:products,product_id',
            'order_item_id' => 'required|exists:order_items,order_item_id',
        ]);

        $userId = auth('api')->id();
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // Verify that the order item belongs to the user and is completed
        $orderItem = OrderItem::with('order')->find($request->order_item_id);
        
        if (!$orderItem) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy OrderItem trong DB.'], 404);
        }
        if ($orderItem->order->user_id !== $userId) {
            return response()->json(['status' => 'error', 'message' => 'Sản phẩm không thuộc đơn hàng của bạn.'], 403);
        }

        $validStatuses = ['completed', 'delivered'];
        if (!in_array($orderItem->order->fulfillment_status, $validStatuses)) {
             return response()->json([
                 'status' => 'error', 
                 'message' => 'Chỉ có thể đánh giá đơn hàng đã hoàn thành. Trạng thái hiện tại: ' . $orderItem->order->fulfillment_status
             ], 400);
        }

        // Verify product matches
        if ($orderItem->product_id != $request->product_id) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Sản phẩm không khớp với đơn hàng. Tham số truyền lên: ' . $request->product_id . ', trong DB: ' . $orderItem->product_id
            ], 400);
        }

        // Check if already reviewed
        $existing = ProductComment::where('order_item_id', $request->order_item_id)->first();
        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'Bạn đã đánh giá sản phẩm này trong đơn hàng rồi.'], 400);
        }

        DB::beginTransaction();
        try {
            $comment = ProductComment::create([
                'product_id' => $request->product_id,
                'user_id' => $userId,
                'order_item_id' => $request->order_item_id,
                'rating' => $request->rating,
                'content' => $request->content,
                'is_approved' => 1,
            ]);

            // Recalculate average rating for the product
            $product = Product::find($request->product_id);
            $avgRating = ProductComment::where('product_id', $product->product_id)
                            ->where('is_approved', 1)
                            ->avg('rating');
            $countRating = ProductComment::where('product_id', $product->product_id)
                            ->where('is_approved', 1)
                            ->count();
            
            $product->rating_avg = round($avgRating, 1);
            $product->rating_count = $countRating;
            $product->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đánh giá sản phẩm thành công.',
                'data' => $comment->load('user:user_id,name,avatar_url')
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of comments for a specific product.
     */
    public function getByProduct($productId)
    {
        $comments = ProductComment::with('user:user_id,full_name,avatar_url')
                        ->where('product_id', $productId)
                        ->where('is_approved', 1)
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);
                        
        return response()->json([
            'status' => 'success',
            'data' => $comments
        ]);
    }
}
