<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Freeship Threshold (VNĐ)
    |--------------------------------------------------------------------------
    | Mức tổng đơn hàng tối thiểu để được miễn phí vận chuyển.
    | Được dùng bởi CartController::upsellSuggestions() và FreeshipBar.vue.
    */
    'freeship_threshold' => env('FREESHIP_THRESHOLD', 500000),

];
