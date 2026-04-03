<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$item = \App\Models\OrderItem::with('order')->find(2);
if ($item) {
    echo json_encode([
        'item' => $item->toArray(),
        'order' => $item->order ? $item->order->toArray() : null
    ], JSON_PRETTY_PRINT);
} else {
    echo "Item 2 not found\n";
}
