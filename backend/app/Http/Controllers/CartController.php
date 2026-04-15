<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;

class CartController extends Controller
{
    /**
     * Lấy user hiện tại (hỗ trợ cả guard api và admin)
     */
    private function getUser()
    {
        return auth('api')->user() ?? auth('admin')->user();
    }

    /**
     * Lấy user_id đúng (hỗ trợ cả guard api và admin)
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
     * Lấy hoặc tạo giỏ hàng active cho user
     */
    private function getOrCreateCart($userId)
    {
        return Cart::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            ['user_id' => $userId, 'status' => 'active']
        );
    }

    /**
     * GET /cart — Lấy giỏ hàng của user hiện tại
     */
    public function getCart()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để xem giỏ hàng!'
            ], 401);
        }

        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'cart_id' => null,
                    'items' => [],
                    'total_items' => 0,
                    'total_price' => 0,
                ]
            ]);
        }

        $cart->load(['items.variant.product.images' => function ($query) {
            $query->where('is_main', 1);
        }]);

        $items = $cart->items->map(function ($item) {
            $variant = $item->variant;
            $product = $variant ? $variant->product : null;
            $mainImage = $product ? $product->images->first() : null;

            return [
                'cart_item_id' => $item->cart_item_id,
                'variant_id' => $item->variant_id,
                'quantity' => $item->quantity,
                'selected' => $item->selected,
                'variant' => $variant ? [
                    'variant_id' => $variant->variant_id,
                    'variant_name' => $variant->variant_name,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'stock' => $variant->stock,
                    'image_url' => $variant->image_url,
                    'status' => $variant->status,
                ] : null,
                'product' => $product ? [
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail_url' => $product->thumbnail_url,
                    'main_image' => $mainImage ? $mainImage->image_url : null,
                ] : null,
                'line_total' => $variant ? $variant->price * $item->quantity : 0,
            ];
        });

        $selectedItems = $items->where('selected', true);

        return response()->json([
            'status' => 'success',
            'data' => [
                'cart_id' => $cart->cart_id,
                'items' => $items->values(),
                'total_items' => $items->sum('quantity'),
                'total_selected_items' => $selectedItems->sum('quantity'),
                'total_price' => $selectedItems->sum('line_total'),
            ]
        ]);
    }

    /**
     * POST /cart/items — Thêm sản phẩm vào giỏ hàng
     */
    public function addItem(Request $request)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để thêm vào giỏ hàng!'
            ], 401);
        }

        $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,variant_id',
            'quantity' => 'required|integer|min:1|max:999',
        ], [
            'variant_id.required' => 'Vui lòng chọn phiên bản sản phẩm.',
            'variant_id.exists' => 'Phiên bản sản phẩm không tồn tại.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng tối thiểu là 1.',
            'quantity.max' => 'Số lượng tối đa là 999.',
        ]);

        // Kiểm tra variant có active không
        $variant = ProductVariant::find($request->variant_id);

        if (!$variant || $variant->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Sản phẩm này hiện không khả dụng.'
            ], 422);
        }

        // Lấy hoặc tạo giỏ hàng
        $cart = $this->getOrCreateCart($userId);

        // Kiểm tra xem variant đã có trong giỏ chưa
        $existingItem = CartItem::where('cart_id', $cart->cart_id)
            ->where('variant_id', $request->variant_id)
            ->first();

        $newQuantity = $existingItem
            ? $existingItem->quantity + $request->quantity
            : $request->quantity;

        // Kiểm tra tồn kho
        if ($newQuantity > $variant->stock) {
            return response()->json([
                'status' => 'error',
                'message' => "Số lượng vượt quá tồn kho. Chỉ còn {$variant->stock} sản phẩm.",
                'available_stock' => $variant->stock,
            ], 422);
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $newQuantity]);
            $message = 'Đã cập nhật số lượng trong giỏ hàng!';
        } else {
            CartItem::create([
                'cart_id' => $cart->cart_id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'selected' => true,
            ]);
            $message = 'Đã thêm sản phẩm vào giỏ hàng!';
        }

        // Đếm tổng items trong giỏ
        $totalItems = CartItem::where('cart_id', $cart->cart_id)->sum('quantity');

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'total_items' => $totalItems,
        ]);
    }

    /**
     * PUT /cart/items/{id} — Cập nhật số lượng hoặc trạng thái selected
     */
    public function updateItem(Request $request, $id)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:999',
            'selected' => 'sometimes|boolean',
        ], [
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng tối thiểu là 1.',
            'quantity.max' => 'Số lượng tối đa là 999.',
        ]);

        // Tìm cart item và kiểm tra quyền sở hữu
        $cartItem = CartItem::where('cart_item_id', $id)
            ->whereHas('cart', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'active');
            })
            ->first();

        if (!$cartItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'
            ], 404);
        }

        // Nếu cập nhật quantity, kiểm tra tồn kho
        if ($request->has('quantity')) {
            $variant = ProductVariant::find($cartItem->variant_id);
            if ($variant && $request->quantity > $variant->stock) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Số lượng vượt quá tồn kho. Chỉ còn {$variant->stock} sản phẩm.",
                    'available_stock' => $variant->stock,
                ], 422);
            }
        }

        $cartItem->update($request->only(['quantity', 'selected']));

        return response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật giỏ hàng!',
        ]);
    }

    /**
     * DELETE /cart/items/{id} — Xóa 1 item khỏi giỏ
     */
    public function removeItem($id)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        $cartItem = CartItem::where('cart_item_id', $id)
            ->whereHas('cart', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'active');
            })
            ->first();

        if (!$cartItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!',
        ]);
    }

    /**
     * DELETE /cart — Xóa toàn bộ giỏ hàng
     */
    public function clearCart()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa toàn bộ giỏ hàng!',
        ]);
    }

    /**
     * PUT /cart/items/{id}/variant — Đổi biến thể (màu/size) của một cart item
     */
    public function changeVariant(Request $request, $id)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,variant_id',
        ]);

        // Tìm cart item hiện tại và kiểm tra quyền sở hữu
        $cartItem = CartItem::where('cart_item_id', $id)
            ->whereHas('cart', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'active');
            })
            ->first();

        if (!$cartItem) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'], 404);
        }

        $newVariant = ProductVariant::find($request->variant_id);

        if (!$newVariant || $newVariant->status !== 'active') {
            return response()->json(['status' => 'error', 'message' => 'Biến thể sản phẩm không khả dụng.'], 422);
        }

        // Kiểm tra variant mới thuộc cùng sản phẩm với variant cũ
        $oldVariant = ProductVariant::find($cartItem->variant_id);
        if (!$oldVariant || $oldVariant->product_id !== $newVariant->product_id) {
            return response()->json(['status' => 'error', 'message' => 'Biến thể không hợp lệ.'], 422);
        }

        // Nếu chọn lại chính variant đang có → không làm gì
        if ($cartItem->variant_id == $request->variant_id) {
            return response()->json(['status' => 'success', 'message' => 'Biến thể không thay đổi.']);
        }

        // Kiểm tra tồn kho
        if ($cartItem->quantity > $newVariant->stock) {
            return response()->json([
                'status' => 'error',
                'message' => "Số lượng vượt quá tồn kho. Chỉ còn {$newVariant->stock} sản phẩm.",
                'available_stock' => $newVariant->stock,
            ], 422);
        }

        // Kiểm tra variant mới đã có sẵn trong giỏ chưa (để merge)
        $cart = Cart::where('user_id', $userId)->where('status', 'active')->first();
        $existingItem = CartItem::where('cart_id', $cart->cart_id)
            ->where('variant_id', $request->variant_id)
            ->where('cart_item_id', '!=', $id)
            ->first();

        if ($existingItem) {
            // Merge: cộng dồn số lượng vào item đã có, xóa item hiện tại
            $mergedQty = $existingItem->quantity + $cartItem->quantity;
            if ($mergedQty > $newVariant->stock) {
                $mergedQty = $newVariant->stock;
            }
            $existingItem->update(['quantity' => $mergedQty]);
            $cartItem->delete();
        } else {
            // Đổi variant_id trực tiếp
            $cartItem->update(['variant_id' => $request->variant_id]);
        }

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật biến thể sản phẩm!']);
    }

    /**
     * GET /cart/count — Lấy số lượng item trong giỏ (dành cho badge header)
     */
    public function getCount()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json(['count' => 0]);
        }

        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        $count = $cart ? $cart->items()->count() : 0;

        return response()->json(['count' => $count]);
    }

    public function buyAgain(Request $request, $orderId)
    {
        $userId = $this->getUserId();

        $order = Order::where('user_id', $userId)->where('order_id', $orderId)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng.'
            ], 404);
        }

        $orderItems = OrderItem::where('order_id', $orderId)->get();
        $cart = $this->getOrCreateCart($userId);
        $totalAdded = 0;
        $errorMessages = [];

        foreach ($orderItems as $orderItem) {
            $variant = ProductVariant::find($orderItem->variant_id);
            if (!$variant || $variant->status !== 'active') {
                $name = $orderItem->product_name;
                if ($orderItem->variant_name) {
                    $name .= ' (' . $orderItem->variant_name . ')';
                }
                $errorMessages[] = "Sản phẩm " . $name . " hiện không còn bán.";
                continue;
            }

            // Kiểm tra xem variant đã có trong giỏ chưa
            $existingItem = CartItem::where('cart_id', $cart->cart_id)
                ->where('variant_id', $variant->variant_id)
                ->first();

            $newQuantity = $existingItem
                ? $existingItem->quantity + $orderItem->quantity
                : $orderItem->quantity;

            // Kiểm tra tồn kho
            if ($newQuantity > $variant->stock) {
                $name = $orderItem->product_name;
                if ($orderItem->variant_name) {
                    $name .= ' (' . $orderItem->variant_name . ')';
                }
                $errorMessages[] = "Số lượng vượt quá tồn kho cho sản phẩm " . $name . ".";
                continue;
            }

            if ($existingItem) {
                $existingItem->update(['quantity' => $newQuantity]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->cart_id,
                    'variant_id' => $variant->variant_id,
                    'quantity' => $orderItem->quantity,
                    'selected' => true,
                ]);
            }
            $totalAdded++;
        }

        // Đếm tổng items trong giỏ
        $totalItems = CartItem::where('cart_id', $cart->cart_id)->sum('quantity');

        if ($totalAdded === 0 && count($errorMessages) > 0) {
            return response()->json([
                'status' => 'error',
                'message' => implode(' ', $errorMessages),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã thêm ' . $totalAdded . ' sản phẩm vào giỏ hàng!',
            'errors'  => $errorMessages,
            'total_items' => $totalItems,
        ]);
    }

    /**
     * GET /cart/upsell-suggestions
     * Trả về mốc Freeship và danh sách sản phẩm gợi ý (phụ kiện cùng danh mục)
     */
    public function upsellSuggestions()
    {
        $freeshipThreshold = (int) config('shop.freeship_threshold', 500000);

        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!',
            ], 401);
        }

        // ── 1. Lấy giỏ hàng active ──────────────────────────────────────────
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$cart || $cart->items()->count() === 0) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'freeship_threshold' => $freeshipThreshold,
                    'suggestions'        => [],
                ],
            ]);
        }

        // ── 2. Tìm sản phẩm có giá cao nhất trong giỏ ───────────────────────
        $cartItems = $cart->items()->with('variant.product')->get();

        $topProduct  = null;
        $topMaxPrice = 0;

        // Danh sách product_id đang trong giỏ (để loại ra khỏi gợi ý)
        $cartProductIds = [];

        foreach ($cartItems as $item) {
            $product = $item->variant?->product;
            if (!$product) continue;

            $cartProductIds[] = $product->product_id;

            $price = (float) ($product->max_price ?? $product->min_price ?? 0);
            if ($price > $topMaxPrice) {
                $topMaxPrice = $price;
                $topProduct  = $product;
            }
        }

        $cartProductIds = array_unique($cartProductIds);

        if (!$topProduct || !$topProduct->category_id) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'freeship_threshold' => $freeshipThreshold,
                    'suggestions'        => [],
                ],
            ]);
        }

        // ── 3. Gợi ý sản phẩm: cùng category, giá thấp hơn, chưa trong giỏ ─
        $suggestions = Product::where('category_id', $topProduct->category_id)
            ->where('status', 'active')
            ->whereNotIn('product_id', $cartProductIds)
            ->where(function ($q) use ($topMaxPrice) {
                $q->where('min_price', '<', $topMaxPrice)
                  ->orWhere('max_price', '<', $topMaxPrice);
            })
            ->whereHas('variants', function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0);
            })
            ->with([
                'variants' => function ($q) {
                    $q->where('status', 'active')
                      ->where('stock', '>', 0)
                      ->orderBy('price', 'asc');
                },
                'mainImage',
            ])
            ->orderByDesc('sold_count')
            ->limit(4)
            ->get();

        // ── 4. Format response ───────────────────────────────────────────────
        $result = $suggestions->map(function ($product) {
            $variant = $product->variants->first();
            if (!$variant) return null;

            $originalPrice  = (float) $variant->price;
            $discountedPrice = round($originalPrice * 0.9); // giảm 10%

            $thumbnail = $product->mainImage?->image_url
                ?? $product->thumbnail_url
                ?? null;

            return [
                'product_id'       => $product->product_id,
                'name'             => $product->name,
                'slug'             => $product->slug,
                'thumbnail_url'    => $thumbnail,
                'original_price'   => $originalPrice,
                'discounted_price' => $discountedPrice,
                'variant_id'       => $variant->variant_id,
                'stock'            => $variant->stock,
            ];
        })->filter()->values();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'freeship_threshold' => $freeshipThreshold,
                'suggestions'        => $result,
            ],
        ]);
    }
}
