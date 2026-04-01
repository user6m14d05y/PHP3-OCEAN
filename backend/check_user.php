<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = \Illuminate\Support\Facades\DB::select("SELECT user_id, email, google_id, deleted_at FROM users WHERE email='daitiensinhnc@gmail.com'");
print_r($users);
