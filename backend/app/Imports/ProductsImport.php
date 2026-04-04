<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * ProductsImport — Import sản phẩm từ file Excel 15 cột
 *
 * Cấu trúc cột:
 *  0  product_key
 *  1  product_type              (variant)
 *  2  category_id
 *  3  brand_id
 *  4  product_name
 *  5  variant_name
 *  6  color
 *  7  size
 *  8  sku
 *  9  price
 * 10  compare_at_price
 * 11  stock
 * 12  short_description
 * 13  description
 * 14  image_urls                (nhiều URL phân tách bởi dấu |)
 */
class ProductsImport implements ToCollection, WithStartRow
{
    protected int $successCount = 0;
    protected array $errors = [];

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'File không có dữ liệu.';
            return;
        }

        $firstRow = $rows->first();
        $firstColValue = strtolower(trim((string)($firstRow[0] ?? '')));
        $thirdColValue = trim((string)($firstRow[2] ?? ''));

        if ($firstColValue === 'simple') {
            $this->errors[] = "File sai định dạng! Đây là file sản phẩm đơn. Vui lòng dùng file mẫu sản phẩm biến thể (15 cột).";
            return;
        }

        if (!empty($thirdColValue) && !is_numeric($thirdColValue)) {
            $this->errors[] = "File sai định dạng! Cột C (category_id) phải là số ID. Giá trị nhận được: [{$thirdColValue}].";
            return;
        }

        // Gom nhóm theo product_key, nếu không có thì fallback theo product_name
        $groupedRows = [];
        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            $productKey = trim((string)($row[0] ?? ''));
            $productType = strtolower(trim((string)($row[1] ?? 'variant')));
            $categoryId = trim((string)($row[2] ?? ''));
            $brandId = trim((string)($row[3] ?? ''));
            $productName = trim((string)($row[4] ?? ''));
            $variantName = trim((string)($row[5] ?? ''));
            $color = trim((string)($row[6] ?? ''));
            $size = trim((string)($row[7] ?? ''));
            $sku = trim((string)($row[8] ?? ''));
            $price = trim((string)($row[9] ?? ''));
            $compareAtPrice = trim((string)($row[10] ?? ''));
            $stock = trim((string)($row[11] ?? '0'));
            $shortDescription = trim((string)($row[12] ?? ''));
            $description = trim((string)($row[13] ?? ''));
            $imageUrls = trim((string)($row[14] ?? ''));

            if (
                $productKey === '' && $productName === '' && $variantName === '' &&
                $categoryId === '' && $sku === '' && $price === ''
            ) {
                continue;
            }

            if ($productType !== 'variant') {
                $this->errors[] = "Dòng {$excelRow}: product_type phải là 'variant'.";
                continue;
            }

            if ($productName === '') {
                $this->errors[] = "Dòng {$excelRow}: Thiếu product_name.";
                continue;
            }

            if ($categoryId === '') {
                $this->errors[] = "Dòng {$excelRow}: Thiếu category_id.";
                continue;
            }

            if (!is_numeric($categoryId)) {
                $this->errors[] = "Dòng {$excelRow}: category_id phải là số.";
                continue;
            }

            if (!\App\Models\Category::where('category_id', $categoryId)->exists()) {
                $this->errors[] = "Dòng {$excelRow}: category_id [{$categoryId}] không tồn tại.";
                continue;
            }

            if ($brandId !== '') {
                if (!is_numeric($brandId) || !\App\Models\Brand::where('brand_id', $brandId)->exists()) {
                    $this->errors[] = "Dòng {$excelRow}: brand_id [{$brandId}] không tồn tại. Đã bỏ qua thương hiệu này.";
                    $brandId = null;
                }
            } else {
                $brandId = null;
            }

            if ($variantName === '') {
                $this->errors[] = "Dòng {$excelRow}: Thiếu variant_name.";
                continue;
            }

            if ($sku === '') {
                $this->errors[] = "Dòng {$excelRow}: Thiếu SKU.";
                continue;
            }

            if ($price === '' || !is_numeric($price) || (float)$price < 0) {
                $this->errors[] = "Dòng {$excelRow}: price không hợp lệ.";
                continue;
            }

            if ($compareAtPrice !== '' && (!is_numeric($compareAtPrice) || (float)$compareAtPrice < 0)) {
                $this->errors[] = "Dòng {$excelRow}: compare_at_price không hợp lệ.";
                continue;
            }

            if ($stock !== '' && !is_numeric($stock)) {
                $this->errors[] = "Dòng {$excelRow}: stock phải là số.";
                continue;
            }

            if (ProductVariant::where('sku', $sku)->exists()) {
                $this->errors[] = "Dòng {$excelRow}: SKU [{$sku}] đã tồn tại trong hệ thống.";
                continue;
            }

            $groupKey = $productKey !== '' ? $productKey : Str::slug($productName);

            $groupedRows[$groupKey][] = [
                'excel_row' => $excelRow,
                'product_key' => $productKey,
                'category_id' => (int)$categoryId,
                'brand_id' => $brandId !== null ? (int)$brandId : null,
                'product_name' => $productName,
                'variant_name' => $variantName,
                'color' => $color,
                'size' => $size,
                'sku' => $sku,
                'price' => (float)$price,
                'compare_at_price' => $compareAtPrice !== '' ? (float)$compareAtPrice : null,
                'stock' => (int)$stock,
                'short_description' => $shortDescription,
                'description' => $description,
                'image_urls' => $imageUrls,
            ];
        }

        foreach ($groupedRows as $groupKey => $items) {
            DB::beginTransaction();

            try {
                $first = $items[0];
                $productName = $first['product_name'];
                $slug = Str::slug($productName) . '-' . Str::lower(Str::random(5));

                $minPrice = min(array_column($items, 'price'));
                $maxPrice = max(array_column($items, 'price'));

                $product = Product::create([
                    'category_id' => $first['category_id'],
                    'brand_id' => $first['brand_id'],
                    'name' => $productName,
                    'slug' => $slug,
                    'short_description' => $first['short_description'],
                    'description' => $first['description'],
                    'product_type' => 'variant',
                    'status' => 'active',
                    'is_featured' => false,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                ]);

                $isFirstImageOfProduct = true;

                foreach ($items as $item) {
                    $barcode = $this->generateUniqueBarcode();

                    $variant = ProductVariant::create([
                        'product_id' => $product->product_id,
                        'sku' => $item['sku'],
                        'barcode' => $barcode,
                        'price' => $item['price'],
                        'compare_at_price' => $item['compare_at_price'],
                        'stock' => $item['stock'],
                        'status' => 'active',
                        // 2 cột dưới chỉ hoạt động nếu bảng product_variants của bạn có sẵn
                        'variant_name' => $item['variant_name'],
                        'attributes' => json_encode([
                            'color' => $item['color'],
                            'size' => $item['size'],
                        ], JSON_UNESCAPED_UNICODE),
                    ]);

                    $this->generateQrCodeImage($barcode);

                    $urls = $this->parseImageUrls($item['image_urls']);
                    foreach ($urls as $idx => $url) {
                        ProductImage::create([
                            'product_id' => $product->product_id,
                            'variant_id' => $variant->variant_id ?? null,
                            'image_url' => $url,
                            'is_main' => $isFirstImageOfProduct && $idx === 0,
                            'sort_order' => $idx + 1,
                        ]);
                    }

                    $isFirstImageOfProduct = false;
                }

                DB::commit();
                $this->successCount++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("[ProductsImport] Group {$groupKey} lỗi: " . $e->getMessage());
                $this->errors[] = "Nhóm [{$groupKey}] lỗi: " . $e->getMessage();
            }
        }
    }

    private function parseImageUrls(?string $imageUrls): array
    {
        if (empty($imageUrls)) {
            return [];
        }

        $items = array_map('trim', explode('|', $imageUrls));
        $items = array_filter($items, fn ($item) => !empty($item));

        return array_values(array_unique($items));
    }

    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'OCN' . strtoupper(Str::random(10)) . rand(10, 99);
        } while (ProductVariant::where('barcode', $barcode)->exists());

        return $barcode;
    }

    private function generateQrCodeImage(string $barcode): string
    {
        $storageDisk = Storage::disk('public');

        if (!$storageDisk->exists('products/qrcodes')) {
            $storageDisk->makeDirectory('products/qrcodes');
        }

        $builder = new Builder(writer: new PngWriter());
        $result = $builder->build(data: $barcode, size: 400, margin: 15);

        $filePath = 'products/qrcodes/' . $barcode . '.png';
        $storageDisk->put($filePath, $result->getString());

        return $filePath;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
