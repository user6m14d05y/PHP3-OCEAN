<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $table = 'shipping_zones';

    protected $fillable = [
        'name',
        'provinces',
        'shipping_fee',
        'free_ship_threshold',
        'delivery_time',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'provinces'           => 'array',
        'shipping_fee'        => 'integer',
        'free_ship_threshold' => 'integer',
        'priority'            => 'integer',
        'is_active'           => 'boolean',
    ];
}
