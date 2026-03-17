<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';
    protected $primaryKey = 'variant_id';
    protected $fillable = [
        'product_id',
        'sku',
        'attribute_name',
        'attribute_value',
        'price_modifier',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
}
