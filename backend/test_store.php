<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fileContext = 'fake image data';
$tmpFilePath = sys_get_temp_dir() . '/fakeimg.jpg';
file_put_contents($tmpFilePath, $fileContext);

$uploadedFile = new Illuminate\Http\UploadedFile(
    $tmpFilePath,
    'fakeimg.jpg',
    'image/jpeg',
    null,
    true // test mode, important to bypass move_uploaded_file check
);

$path = $uploadedFile->store('products/thumbnails', 'public');
var_dump($path);
if (!$path) {
    echo "isValid: " . ($uploadedFile->isValid() ? 'true' : 'false') . "\n";
    echo "error: " . $uploadedFile->getErrorMessage() . "\n";
}
