<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductComment extends Model
{
    protected $table = 'product_comments';
    protected $primaryKey = 'comment_id';

    protected $fillable = [
        'product_id',
        'user_id',
        'order_item_id',
        'rating',
        'content',
        'is_approved',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
