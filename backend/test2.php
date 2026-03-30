<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
echo 'FACEBOOK_SECRET is: ' . env('FACEBOOK_SECRET') . "\n";
echo 'Config DB_HOST is: ' . config('database.connections.mysql.host') . "\n";
