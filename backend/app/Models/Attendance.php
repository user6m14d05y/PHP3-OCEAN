<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendances';

    protected $fillable = [
        'user_id',
        'check_in_at',
        'check_out_at',
        'ip_address',
        'latitude',
        'longitude',
        'image_path',
        'note',
    ];
}
