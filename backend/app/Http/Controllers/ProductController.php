<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\CartItem;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class ProductController extends Controller
{
    /**
     * Tạo mã barcode duy nhất cho biến thể
     */
    private function generateUniqueBarcode(): string
    {
        do {
            $barcode = 'OCN' . strtoupper(Str::random(10)) . rand(10, 99);
        } while (ProductVariant::where('barcode', $barcode)->exists());
        return $barcode;
    }

    /**
     * Tạo QR code PNG từ barcode và lưu vào storage
     * @return string Đường dẫn file QR code (relative to public disk)
     */
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

    /**
     * Admin: danh sách sản phẩm (phân trang, tìm kiếm, lọc status)
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 12);
        $search = $request->query('search', '');
        $status = $request->query('status', '');
        $offset = ($page - 1) * $limit;

        $query = Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'stock', 'product_id');
            },
            'category:category_id,name',
            'brand:brand_id,name',
        ])->withSum('variants', 'stock');

        if ($search) {
            $query->where(function ($q) use ($search)
            {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['draft', 'active', 'inactive', 'out_of_stock'])) {
            $query->where('status', $status);
        }

        // Lọc theo danh mục (bao gồm cả danh mục con)
        $categoryId = $request->query('category_id');
        if ($categoryId && $categoryId !== 'All') {
            $categoryIds = [$categoryId];
            $childIds = \App\Models\Category::where('parent_id', $categoryId)->pluck('category_id')->toArray();
            $categoryIds = array_merge($categoryIds, $childIds);
            $query->whereIn('category_id', $categoryIds);
        }

        // Lọc theo giá
        $priceRange = $request->query('price_range');
        if ($priceRange === 'under-500k') {
            $query->where('min_price', '<', 500000);
        } elseif ($priceRange === '500k-1m') {
            $query->whereBetween('min_price', [500000, 1000000]);
        } elseif ($priceRange === 'above-1m') {
            $query->where('min_price', '>', 1000000);
        }

        // Sắp xếp
        $sortBy = $request->query('sort_by');
        if ($sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sortBy === 'price-asc') {
            $query->orderBy('min_price', 'asc');
        } elseif ($sortBy === 'price-desc') {
            $query->orderBy('min_price', 'desc');
        } else {
            // newest hoặc default
            $query->orderBy('product_id', 'desc');
        }

        $total = $query->count();
        $products = $query->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products,
            'total' => $total,
            'total_pages' => ceil($total / $limit),
            'page' => (int) $page,
            'limit' => (int) $limit,
        ]);
    }
    public function productFeatured(Request $request)
    {
        $query = Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'stock', 'product_id');
            },
            'category:category_id,name',
            'brand:brand_id,name',
        ]);
        $products = $query->orderBy('product_id', 'desc')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->limit(4)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $products
        ]);
    }


    /**
     * Chi tiết sản phẩm theo slug (client)
     */
    public function show($slug)
    {
        $product = Product::with(['category', 'brand', 'images', 'variants'])
            ->where('slug', $slug)
            ->first();

        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    /**

     * Danh sách sản phẩm nổi bật
     */
    public function featured()
    {
        $products = Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'stock', 'product_id');
            }
        ])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->get();
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**

     * Danh sách tất cả sản phẩm (public, phân trang)
     */
    public function all(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 12);
        $offset = ($page - 1) * $limit;
        $products = Product::with([
            'mainImage' => function ($q) {
                $q->select('image_id', 'image_url', 'product_id');
            },
            'lowestPriceVariant' => function ($q) {
                $q->select('variant_id', 'price', 'stock', 'product_id');
            }
        ])
            ->where('status', 'active')
            ->offset($offset)
            ->limit($limit)
            ->get();
        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Lấy chi tiết sản phẩm theo ID (admin edit)
     */
    public function edit($id)
    {
        $product = Product::with(['category', 'brand', 'images', 'variants'])
            ->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Thêm sản phẩm mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:200',
            'category_id'       => 'required|exists:categories,category_id',
            'brand_id'          => 'nullable|exists:brands,brand_id',
            'seller_id'         => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'product_type'      => 'required|in:simple,variant',
            'status'            => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured'       => 'boolean',
            'thumbnail'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'gallery'           => 'nullable|array',
            'gallery.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price'             => 'nullable|numeric|min:0',
            'compare_at_price'  => 'nullable|numeric|min:0',
            'stock'             => 'nullable|integer|min:0',
            'variants'          => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 0. Đảm bảo thư mục storage tồn tại
            $storageDisk = Storage::disk('public');
            foreach (['products/thumbnails', 'products/gallery', 'products/variants'] as $dir) {
                if (!$storageDisk->exists($dir)) {
                    $storageDisk->makeDirectory($dir);
                }
            }

            // 1. Parse variants JSON (FE gửi dạng JSON string)
            $variantsData = [];
            if ($request->filled('variants')) {
                $variantsData = json_decode($request->variants, true);
                if (!is_array($variantsData)) {
                    return response()->json(['message' => 'Dữ liệu variants không hợp lệ.'], 422);
                }
            }

            // 2. Upload thumbnail
            $thumbnailPath = null;
            Log::info('[ProductStore] hasFile thumbnail: ' . ($request->hasFile('thumbnail') ? 'YES' : 'NO'));
            Log::info('[ProductStore] all files: ', array_keys($request->allFiles()));

            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                Log::info('[ProductStore] thumbnail file info', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ]);

                $thumbnailPath = $file->store('products/thumbnails', 'public');
                Log::info('[ProductStore] store() returned: ' . var_export($thumbnailPath, true));

                if (!$thumbnailPath || $thumbnailPath === false) {
                    $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                    throw new \Exception('Lỗi lưu thumbnail: ' . $reason);
                }
            }

            // 3. Tạo sản phẩm
            $slug = Str::slug($request->name) . '-' . Str::random(5);
            $product = Product::create([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id ?: null,
                'seller_id'         => $request->seller_id ?: null,
                'name'              => $request->name,
                'slug'              => $slug,
                'short_description' => $request->short_description,
                'description'       => $request->description,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $request->product_type,
                'status'            => $request->status,
                'is_featured'       => $request->boolean('is_featured'),
                'min_price'         => 0,
                'max_price'         => 0,
            ]);

            // 4. Main image → product_images
            if ($thumbnailPath) {
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            }

            // 5. Gallery images
            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    if (!$path || $path === false) {
                        $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                        throw new \Exception('Lỗi lưu ảnh gallery: ' . $reason);
                    }
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            // 6. Tạo variants
            $allPrices = [];

            if ($request->product_type === 'simple') {
                $price = $request->price ?? 0;
                $allPrices[] = $price;
                $barcode = $this->generateUniqueBarcode();
                ProductVariant::create([
                    'product_id'       => $product->product_id,
                    'sku'              => $slug . '-default',
                    'barcode'          => $barcode,
                    'price'            => $price,
                    'compare_at_price' => $request->compare_at_price,
                    'stock'            => $request->stock ?? 0,
                    'status'           => 'active',
                ]);
                $this->generateQrCodeImage($barcode);
            } else {
                // Variant product: mỗi color có nhiều sizes
                // FE gửi: [{ color: "Đỏ", sizes: [{ size: "M", price: 100, stock: 10 }, ...] }, ...]
                $combinations = [];

                foreach ($variantsData as $vIndex => $vData) {
                    $color = $vData['color'] ?? null;
                    $sizes = $vData['sizes'] ?? [];

                    // Upload variant images (nhiều ảnh mỗi biến thể)
                    $variantImagePaths = [];
                    if ($request->hasFile("variant_images.{$vIndex}")) {
                        foreach ($request->file("variant_images.{$vIndex}") as $imgFile) {
                            $imgPath = $imgFile->store('products/variants', 'public');
                            if (!$imgPath || $imgPath === false) {
                                $reason = !$imgFile->isValid() ? $imgFile->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                                throw new \Exception('Lỗi lưu ảnh biến thể: ' . $reason);
                            }
                            $variantImagePaths[] = $imgPath;
                        }
                    }

                    foreach ($sizes as $sData) {
                        $size = $sData['size'] ?? null;
                        $combo = strtolower(trim($color ?? '')) . '|' . strtolower(trim($size ?? ''));

                        if (in_array($combo, $combinations)) {
                            throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                        }
                        $combinations[] = $combo;

                        $vPrice = $sData['price'] ?? 0;
                        $allPrices[] = $vPrice;

                        $barcode = $this->generateUniqueBarcode();
                        $variant = ProductVariant::create([
                            'product_id' => $product->product_id,
                            'sku'        => $slug . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                            'barcode'    => $barcode,
                            'color'      => $color,
                            'size'       => $size,
                            'price'      => $vPrice,
                            'stock'      => $sData['stock'] ?? 0,
                            'image_url'  => $variantImagePaths[0] ?? null,
                            'status'     => 'active',
                        ]);
                        $this->generateQrCodeImage($barcode);

                        // Lưu ảnh biến thể vào product_images
                        foreach ($variantImagePaths as $imgIndex => $imgPath) {
                            ProductImage::create([
                                'product_id' => $product->product_id,
                                'variant_id' => $variant->variant_id,
                                'image_url'  => $imgPath,
                                'is_main'    => false,
                                'sort_order' => $imgIndex + 1,
                            ]);
                        }
                    }
                }
            }

            // 7. Update min/max price
            if (count($allPrices) > 0) {
                $product->update([
                    'min_price' => min($allPrices),
                    'max_price' => max($allPrices),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thêm sản phẩm thành công.',
                'data'    => $product->load('variants', 'images'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Thêm sản phẩm thất bại: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name'              => 'required|string|max:200',
            'category_id'       => 'required|exists:categories,category_id',
            'brand_id'          => 'nullable|exists:brands,brand_id',
            'seller_id'         => 'nullable|exists:users,user_id',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'product_type'      => 'required|in:simple,variant',
            'status'            => 'required|in:draft,active,inactive,out_of_stock',
            'is_featured'       => 'boolean',
            'thumbnail'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'price'             => 'nullable|numeric|min:0',
            'compare_at_price'  => 'nullable|numeric|min:0',
            'stock'             => 'nullable|integer|min:0',
            'gallery'           => 'nullable|array',
            'gallery.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'deleted_gallery_ids'   => 'nullable|array',
            'deleted_gallery_ids.*' => 'integer',
            'variants'              => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // 0. Đảm bảo thư mục storage tồn tại
            $storageDisk = Storage::disk('public');
            foreach (['products/thumbnails', 'products/gallery', 'products/variants'] as $dir) {
                if (!$storageDisk->exists($dir)) {
                    $storageDisk->makeDirectory($dir);
                }
            }

            // 1. Parse variants JSON
            $variantsData = [];
            if ($request->filled('variants')) {
                $variantsData = json_decode($request->variants, true);
                if (!is_array($variantsData)) {
                    return response()->json(['message' => 'Dữ liệu variants không hợp lệ.'], 422);
                }
            }

            // 2. Thumbnail
            $thumbnailPath = $product->thumbnail_url;
            if ($request->hasFile('thumbnail')) {
                if ($thumbnailPath && is_string($thumbnailPath) && $thumbnailPath !== '0') {
                    Storage::disk('public')->delete($thumbnailPath);
                }
                $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails', 'public');
                if (!$thumbnailPath || $thumbnailPath === false) {
                    $file = $request->file('thumbnail');
                    $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                    throw new \Exception('Lỗi lưu thumbnail: ' . $reason);
                }

                // Update main image
                ProductImage::where('product_id', $product->product_id)
                    ->where('is_main', true)->delete();

                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            } else {
                // Nếu không upload ảnh mới và thumbnail cũ bị lỗi (là "0"), reset về null
                if ($thumbnailPath === '0' || $thumbnailPath === 0) {
                    $thumbnailPath = null;
                }
            }

            // 3. Update product info
            $product->update([
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id ?: null,
                'seller_id'         => $request->seller_id ?: null,
                'name'              => $request->name,
                'slug'              => $request->slug ?: Str::slug($request->name),
                'short_description' => $request->short_description,
                'description'       => $request->description,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $request->product_type,
                'status'            => $request->status,
                'is_featured'       => $request->boolean('is_featured'),
            ]);

            // 4. Xóa gallery cũ (nếu có)
            if ($request->filled('deleted_gallery_ids')) {
                $ids = $request->deleted_gallery_ids;
                $imagesToDelete = ProductImage::whereIn('image_id', $ids)
                    ->where('product_id', $product->product_id)->get();
                foreach ($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_url);
                    $img->delete();
                }
            }

            // 5. Thêm gallery mới
            if ($request->hasFile('gallery')) {
                $maxSort = ProductImage::where('product_id', $product->product_id)->max('sort_order') ?? 0;
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    if (!$path || $path === false) {
                        $reason = !$file->isValid() ? $file->getErrorMessage() : 'Kiểm tra quyền ghi thư mục storage.';
                        throw new \Exception('Lỗi lưu ảnh gallery: ' . $reason);
                    }
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $maxSort + $i + 1,
                    ]);
                }
            }

            // 6. Xử lý variants
            $allPrices = [];

            if ($request->product_type === 'simple') {
                $price = $request->price ?? 0;
                $stock = $request->stock ?? 0;
                $allPrices[] = $price;

                $defaultVariant = $product->variants()->first();
                if ($defaultVariant) {
                    $updateData = [
                        'price'            => $price,
                        'compare_at_price' => $request->compare_at_price,
                        'stock'            => $stock,
                    ];
                    // Nếu chưa có barcode thì tạo mới
                    if (empty($defaultVariant->barcode)) {
                        $newBarcode = $this->generateUniqueBarcode();
                        $updateData['barcode'] = $newBarcode;
                        $this->generateQrCodeImage($newBarcode);
                    }
                    $defaultVariant->update($updateData);
                } else {
                    $barcode = $this->generateUniqueBarcode();
                    ProductVariant::create([
                        'product_id' => $product->product_id,
                        'sku'        => Str::slug($product->name) . '-default',
                        'barcode'    => $barcode,
                        'price'            => $price,
                        'compare_at_price' => $request->compare_at_price,
                        'stock'            => $stock,
                        'status'     => 'active',
                    ]);
                    $this->generateQrCodeImage($barcode);
                }
            } else {
                // Variant product
                $existingVariantIds = $product->variants()->pluck('variant_id')->toArray();

                // Xóa cart_items tham chiếu đến variants cũ (tránh FK constraint)
                if (!empty($existingVariantIds)) {
                    CartItem::whereIn('variant_id', $existingVariantIds)->delete();
                }

                // 1. Xóa ảnh biến thể mà user đã ấn nút xóa thủ công
                $deletedImageIds = $request->input('deleted_variant_image_ids', []);
                if (!empty($deletedImageIds)) {
                    $imagesToDelete = ProductImage::whereIn('image_id', $deletedImageIds)->get();
                    foreach ($imagesToDelete as $img) {
                        Storage::disk('public')->delete($img->image_url);
                        $img->delete();
                    }
                }

                // 2. QUAN TRỌNG: Set variant_id = NULL cho ảnh TRƯỚC khi xóa variant
                // (vì FK ON DELETE CASCADE sẽ xóa ảnh theo variant nếu không)
                $oldVariantImagesMap = []; // color => [ProductImage records]
                foreach ($product->variants as $oldVariant) {
                    $color = $oldVariant->color ?? 'default';
                    if (!isset($oldVariantImagesMap[$color])) {
                        $oldVariantImagesMap[$color] = [];
                    }
                    $variantOldImages = ProductImage::where('product_id', $product->product_id)
                        ->where('variant_id', $oldVariant->variant_id)
                        ->get();
                    foreach ($variantOldImages as $img) {
                        $img->update(['variant_id' => null]); // Tạm tách khỏi variant
                        $oldVariantImagesMap[$color][] = $img;
                    }
                }

                // 3. Xóa tất cả variant cũ (ảnh đã được tách ra, không bị CASCADE xóa)
                ProductVariant::where('product_id', $product->product_id)->delete();

                // 4. Tạo lại variant mới
                $combinations = [];
                foreach ($variantsData as $vIndex => $vData) {
                    $color = $vData['color'] ?? null;
                    $sizes = $vData['sizes'] ?? [];

                    // Upload variant images MỚI
                    $variantImagePaths = [];
                    if ($request->hasFile("variant_images.{$vIndex}")) {
                        foreach ($request->file("variant_images.{$vIndex}") as $imgFile) {
                            $imgPath = $imgFile->store('products/variants', 'public');
                            if (!$imgPath || $imgPath === false) {
                                throw new \Exception('Lỗi lưu ảnh biến thể.');
                            }
                            $variantImagePaths[] = $imgPath;
                        }
                    }

                    // Lấy ảnh cũ còn tồn tại cho color này
                    $colorKey = $color ?? 'default';
                    $existingColorImages = $oldVariantImagesMap[$colorKey] ?? [];

                    // Xác định image_url cho variant
                    $mainImageUrl = null;
                    if (!empty($existingColorImages)) {
                        $mainImageUrl = $existingColorImages[0]->image_url;
                    } elseif (!empty($variantImagePaths)) {
                        $mainImageUrl = $variantImagePaths[0];
                    }

                    $firstVariantForColor = null;

                    foreach ($sizes as $sData) {
                        $size = $sData['size'] ?? null;
                        $combo = strtolower(trim($color ?? '')) . '|' . strtolower(trim($size ?? ''));

                        if (in_array($combo, $combinations)) {
                            throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                        }
                        $combinations[] = $combo;

                        $vPrice = $sData['price'] ?? 0;
                        $allPrices[] = $vPrice;

                        $barcode = $this->generateUniqueBarcode();
                        $variant = ProductVariant::create([
                            'product_id' => $product->product_id,
                            'sku'        => Str::slug($product->name) . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                            'barcode'    => $barcode,
                            'color'      => $color,
                            'size'       => $size,
                            'price'      => $vPrice,
                            'stock'      => $sData['stock'] ?? 0,
                            'image_url'  => $mainImageUrl,
                            'status'     => 'active',
                        ]);
                        $this->generateQrCodeImage($barcode);

                        if (!$firstVariantForColor) {
                            $firstVariantForColor = $variant;
                        }
                    }

                    // Gán ảnh cũ + ảnh mới cho variant ĐẦU TIÊN của màu này
                    if ($firstVariantForColor) {
                        // Cập nhật ảnh cũ → gán variant_id mới
                        foreach ($existingColorImages as $oldImg) {
                            $oldImg->update(['variant_id' => $firstVariantForColor->variant_id]);
                        }
                        // Tạo record cho ảnh mới upload
                        foreach ($variantImagePaths as $imgIndex => $imgPath) {
                            ProductImage::create([
                                'product_id' => $product->product_id,
                                'variant_id' => $firstVariantForColor->variant_id,
                                'image_url'  => $imgPath,
                                'is_main'    => false,
                                'sort_order' => count($existingColorImages) + $imgIndex + 1,
                            ]);
                        }
                    }
                }
            }

            // 7. Update min/max price
            if (count($allPrices) > 0) {
                $product->update([
                    'min_price' => min($allPrices),
                    'max_price' => max($allPrices),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công.',
                'data'    => $product->load('variants', 'images'),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật thất bại: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa sản phẩm (soft delete)
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            return response()->json([
                'status'  => 'success',
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete: ' . $e->getMessage(),
            ], 500);
        }
    }
}
