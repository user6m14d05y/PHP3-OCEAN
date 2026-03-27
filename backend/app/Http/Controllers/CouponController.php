<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
<<<<<<< HEAD
use App\Models\UserCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
=======
use Illuminate\Http\Request;
>>>>>>> origin/binhbc

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $coupons = Coupon::all();
        return response()->json([
            'status' => 'success',
            'data' => $coupons
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code',
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
        ]);

        $coupon = Coupon::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá đã được tạo thành công',
            'data' => $coupon
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code,' . $id,
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
        ]);

        $coupon->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $coupon->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa mã giảm giá thành công!'
        ]);
    }
<<<<<<< HEAD

    /**
     * Lấy danh sách mã giảm giá công khai cho khách hàng
     */
    public function getPublicCoupons()
    {
        $now = now();
        $coupons = Coupon::where('is_public', true)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $coupons
        ]);
    }

    /**
     * Khách hàng lưu mã giảm giá
     */
    public function saveCoupon(Request $request)
    {
        $user = auth('api')->user() ?? auth('admin')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để lưu mã giảm giá!'
            ], 401);
        }

        $couponId = $request->input('coupon_id');
        $coupon = Coupon::find($couponId);

        if (!$coupon || !$coupon->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn!'
            ], 404);
        }

        // Kiểm tra xem đã lưu chưa
        $userCoupon = UserCoupon::where('user_id', $user->user_id)
            ->where('coupon_id', $couponId)
            ->first();

        if ($userCoupon) {
            return response()->json([
                'status' => 'info',
                'message' => 'Bạn đã lưu mã giảm giá này rồi!'
            ]);
        }

        UserCoupon::create([
            'user_id' => $user->user_id,
            'coupon_id' => $couponId,
            'is_saved' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu mã giảm giá thành công!'
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá của tôi (đã lưu)
     */
    public function getUserCoupons()
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        // Lấy các user_coupons kèm thông tin coupon
        $userCoupons = UserCoupon::with('coupon')
            ->where('user_id', $user->user_id)
            ->where('is_saved', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $userCoupons->pluck('coupon')
        ]);
    }
=======
>>>>>>> origin/binhbc
}
