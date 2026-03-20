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
        'barcode',
        'variant_name',
        'color',
        'size',
        'material',
        'weight_gram',
        'cost_price',
        'price',
        'compare_at_price',
        'stock',
        'reserved_stock',
        'safety_stock',
        'image_url',
        'status',
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
