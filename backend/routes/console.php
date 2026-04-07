<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ===== Scheduled Tasks =====

// Tự động hủy đơn VNPay/MoMo chưa thanh toán sau 30 phút
// Chạy mỗi 5 phút để phát hiện sớm — command tự filter theo thời gian
Schedule::command('orders:cancel-expired-vnpay --minutes=30')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cancel-expired-orders.log'));
