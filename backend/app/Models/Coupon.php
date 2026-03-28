<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_discount_value',
        'min_order_value',
        'usage_limit',
        'used_count',
        'user_usage_limit',
        'is_public',
        'is_first_order',
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * Danh mục áp dụng (nếu trống = áp dụng tất cả sản phẩm)
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_categories', 'coupon_id', 'category_id');
    }

    /**
     * Danh sách user đã lưu/dùng coupon này
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class);
    }
}
