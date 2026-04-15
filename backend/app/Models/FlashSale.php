<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class FlashSale extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'title',
        'description',
        'total_stock',
        'sold_count',
        'sale_price',
        'original_price',
        'max_per_user',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'sale_price'     => 'float',
        'original_price' => 'float',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'variant_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Chỉ lấy Flash Sale đang active trong khung giờ hiện tại.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('starts_at', '<=', now())
                     ->where('ends_at', '>=', now());
    }

    /**
     * Chỉ lấy Flash Sale sắp diễn ra (upcoming).
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'active')
                     ->where('starts_at', '>', now());
    }

    // ── Redis Helpers ────────────────────────────────────────────────────────

    /**
     * Redis key cho tồn kho của flash sale này.
     */
    public function stockKey(): string
    {
        return "flash_sale_stock_{$this->id}";
    }

    /**
     * Nạp stock từ DB vào Redis (gọi khi bắt đầu chiến dịch).
     */
    public function seedStockToRedis(): void
    {
        $remaining = $this->total_stock - $this->sold_count;
        Redis::set($this->stockKey(), max(0, $remaining));
        // TTL: kéo dài đến 1 giờ sau khi kết thúc chiến dịch
        $ttl = now()->diffInSeconds($this->ends_at) + 3600;
        Redis::expire($this->stockKey(), $ttl);
    }

    /**
     * Lấy tồn kho hiện tại từ Redis (fallback MySQL nếu key không tồn tại).
     */
    public function getRemainingStock(): int
    {
        $key = $this->stockKey();
        if (Redis::exists($key)) {
            return (int) max(0, Redis::get($key));
        }
        // Fallback: tính từ MySQL
        return max(0, $this->total_stock - $this->sold_count);
    }

    // ── Computed Attributes ──────────────────────────────────────────────────

    /**
     * Phần trăm giảm giá.
     */
    public function getDiscountPercentAttribute(): int
    {
        if ($this->original_price <= 0) return 0;
        return (int) round((($this->original_price - $this->sale_price) / $this->original_price) * 100);
    }
}
