<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

try {
    $tmpPath = sys_get_temp_dir() . '/dummy2.jpg';
    file_put_contents($tmpPath, 'dummy image content');
    
    $file = new UploadedFile($tmpPath, 'dummy2.jpg', 'image/jpeg', null, true);
    
    // TEMPORARILY FORCE THROW TO TRUE
    config(['filesystems.disks.public.throw' => true]);
    
    $path = Storage::disk('public')->putFile('products/thumbnails', $file);
    
    if ($path === false) {
        echo "store() returned FALSE!\n";
    } else {
        echo "store() succeeded! Path: $path\n";
    }
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
