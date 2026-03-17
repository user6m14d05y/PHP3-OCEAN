<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = [
        'category_id',
        'brand_id',
        'seller_id',
        'name',
        'slug',
        'short_description',
        'description',
        'base_price',
        'is_active',
        'is_featured',
        'created_at',
        'updated_at',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'user_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'product_id');
    }

    public function comments()
    {
        return $this->hasMany(ProductComment::class, 'product_id', 'product_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'product_id', 'product_id');
    }
    public function mainImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('is_main', 1);
    }
    public function lowestPriceVariant()
    {
        return $this->hasOne(ProductVariant::class, 'product_id')->orderBy('price', 'asc');
    }
}
