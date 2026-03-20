<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
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
        ]);

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

        $total = $query->count();
        $products = $query->orderBy('product_id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products,
            'total' => $total,
            'page' => (int) $page,
            'limit' => (int) $limit,
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
        $validated = $request->validate([
            'name'              => 'required|string|max:200',
            'slug'              => 'required|string|max:220|unique:products,slug',
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

            // Simple product
            'price'             => 'nullable|numeric|min:0',
            'compare_at_price'  => 'nullable|numeric|min:0',
            'stock'             => 'nullable|integer|min:0',

            // Variant product
            'variants'                    => 'nullable|array',
            'variants.*.color'            => 'nullable|string|max:60',
            'variants.*.size'             => 'nullable|string|max:60',
            'variants.*.material'         => 'nullable|string|max:120',
            'variants.*.price'            => 'nullable|numeric|min:0',
            'variants.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.cost_price'       => 'nullable|numeric|min:0',
            'variants.*.stock'            => 'nullable|integer|min:0',
            'variants.*.image'            => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            // Alternative: nested sizes
            'variants.*.sizes'            => 'nullable|array',
            'variants.*.sizes.*.size'     => 'nullable|string|max:60',
            'variants.*.sizes.*.price'    => 'nullable|numeric|min:0',
            'variants.*.sizes.*.stock'    => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // 1) Thumbnail
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails', 'public');
            }

            // 2) Create product
            $product = Product::create([
                'category_id'       => $validated['category_id'],
                'brand_id'          => $validated['brand_id'] ?? null,
                'seller_id'         => $validated['seller_id'] ?? null,
                'name'              => $validated['name'],
                'slug'              => Str::slug($validated['name'], '-'),
                'short_description' => $validated['short_description'] ?? null,
                'description'       => $validated['description'] ?? null,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $validated['product_type'],
                'status'            => $validated['status'],
                'is_featured'       => $validated['is_featured'] ?? false,
                'min_price'         => 0,
                'max_price'         => 0,
            ]);

            // 3) Main image → product_images
            if ($thumbnailPath) {
                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            }

            // 4) Gallery images
            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $i + 1,
                    ]);
                }
            }

            // 5) Variants
            $allPrices = [];
            if ($validated['product_type'] === 'simple') {
                $price = $validated['price'] ?? 0;
                $allPrices[] = $price;
                ProductVariant::create([
                    'product_id'       => $product->product_id,
                    'sku'              => Str::slug($validated['slug']) . '-default',
                    'price'            => $price,
                    'compare_at_price' => $validated['compare_at_price'] ?? null,
                    'stock'            => $validated['stock'] ?? 0,
                    'status'           => 'active',
                ]);
            } else {
                $variantsData = $request->input('variants', []);
                $variantFiles = $request->file('variants', []);
                $combinations = [];

                foreach ($variantsData as $idx => $vData) {
                    // Upload variant image
                    $variantImgPath = null;
                    if (isset($variantFiles[$idx]['image'])) {
                        $variantImgPath = $variantFiles[$idx]['image']->store('products/variants', 'public');
                    }

                    // Check if this variant uses nested sizes
                    $sizes = $vData['sizes'] ?? [];
                    if (count($sizes) > 0) {
                        foreach ($sizes as $sData) {
                            $color = $vData['color'] ?? null;
                            $size  = $sData['size'] ?? null;
                            $combo = ($color ?? '') . '|' . ($size ?? '');

                            if (in_array($combo, $combinations)) {
                                throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                            }
                            $combinations[] = $combo;

                            $vPrice = $sData['price'] ?? 0;
                            $allPrices[] = $vPrice;

                            $variant = ProductVariant::create([
                                'product_id' => $product->product_id,
                                'sku'        => Str::slug($validated['slug']) . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                                'color'      => $color,
                                'size'       => $size,
                                'material'   => $vData['material'] ?? null,
                                'price'      => $vPrice,
                                'cost_price' => $sData['cost_price'] ?? 0,
                                'stock'      => $sData['stock'] ?? 0,
                                'image_url'  => $variantImgPath,
                                'status'     => 'active',
                            ]);

                            if ($variantImgPath) {
                                ProductImage::create([
                                    'product_id' => $product->product_id,
                                    'variant_id' => $variant->variant_id,
                                    'image_url'  => $variantImgPath,
                                    'is_main'    => false,
                                    'sort_order' => 1,
                                ]);
                            }
                        }
                    } else {
                        // Flat variant (no nested sizes)
                        $color = $vData['color'] ?? null;
                        $size  = $vData['size'] ?? null;
                        $combo = ($color ?? '') . '|' . ($size ?? '');

                        if (in_array($combo, $combinations)) {
                            throw new \Exception("Biến thể trùng lặp: Màu [{$color}] - Size [{$size}]");
                        }
                        $combinations[] = $combo;

                        $vPrice = $vData['price'] ?? 0;
                        $allPrices[] = $vPrice;

                        $variant = ProductVariant::create([
                            'product_id'       => $product->product_id,
                            'sku'              => Str::slug($validated['slug']) . '-' . Str::slug($color ?? 'def') . '-' . Str::slug($size ?? 'def') . '-' . Str::random(4),
                            'color'            => $color,
                            'size'             => $size,
                            'material'         => $vData['material'] ?? null,
                            'price'            => $vPrice,
                            'compare_at_price' => $vData['compare_at_price'] ?? null,
                            'cost_price'       => $vData['cost_price'] ?? 0,
                            'stock'            => $vData['stock'] ?? 0,
                            'image_url'        => $variantImgPath,
                            'status'           => 'active',
                        ]);

                        if ($variantImgPath) {
                            ProductImage::create([
                                'product_id' => $product->product_id,
                                'variant_id' => $variant->variant_id,
                                'image_url'  => $variantImgPath,
                                'is_main'    => false,
                                'sort_order' => 1,
                            ]);
                        }
                    }
                }
            }

            // 6) Update min/max price
            if (count($allPrices) > 0) {
                $product->update([
                    'min_price' => min($allPrices),
                    'max_price' => max($allPrices),
                ]);
            }

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Product created successfully',
                'data'    => $product->load('variants', 'images'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'              => 'required|string|max:200',
            'slug'              => 'required|string|max:220|unique:products,slug,' . $product->product_id . ',product_id',
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
            'stock'             => 'nullable|integer|min:0',
            'variants'          => 'nullable|array',
            'variants.*.variant_id' => 'nullable|exists:product_variants,variant_id',
            'variants.*.price'  => 'nullable|numeric|min:0',
            'variants.*.stock'  => 'nullable|integer|min:0',
            'variants.*.status' => 'nullable|string',
            'variants.*.image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'gallery'           => 'nullable|array',
            'gallery.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'deleted_gallery_ids' => 'nullable|array',
            'deleted_gallery_ids.*' => 'integer',
        ]);

        DB::beginTransaction();
        try {
            // Handle thumbnail update
            $thumbnailPath = $product->thumbnail_url;
            if ($request->hasFile('thumbnail')) {
                // Delete old thumbnail
                if ($thumbnailPath) {
                    Storage::disk('public')->delete($thumbnailPath);
                }
                $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails', 'public');

                // Update main image in product_images
                ProductImage::where('product_id', $product->product_id)
                    ->where('is_main', true)
                    ->delete();

                ProductImage::create([
                    'product_id' => $product->product_id,
                    'image_url'  => $thumbnailPath,
                    'is_main'    => true,
                    'sort_order' => 0,
                ]);
            }

            $product->update([
                'category_id'       => $validated['category_id'],
                'brand_id'          => $validated['brand_id'] ?? null,
                'seller_id'         => $validated['seller_id'] ?? null,
                'name'              => $validated['name'],
                'slug'              => $validated['slug'],
                'short_description' => $validated['short_description'] ?? null,
                'description'       => $validated['description'] ?? null,
                'thumbnail_url'     => $thumbnailPath,
                'product_type'      => $validated['product_type'],
                'status'            => $validated['status'],
                'is_featured'       => $validated['is_featured'] ?? false,
            ]);

            // Xoá gallery cũ
            if (!empty($validated['deleted_gallery_ids'])) {
                $imagesToDelete = ProductImage::whereIn('image_id', $validated['deleted_gallery_ids'])
                    ->where('product_id', $product->product_id)
                    ->get();
                foreach($imagesToDelete as $img) {
                    Storage::disk('public')->delete($img->image_url);
                    $img->delete();
                }
            }

            // Thêm gallery mới
            if ($request->hasFile('gallery')) {
                $maxSortOrder = ProductImage::where('product_id', $product->product_id)->max('sort_order') ?? 0;
                foreach ($request->file('gallery') as $i => $file) {
                    $path = $file->store('products/gallery', 'public');
                    ProductImage::create([
                        'product_id' => $product->product_id,
                        'image_url'  => $path,
                        'is_main'    => false,
                        'sort_order' => $maxSortOrder + $i + 1,
                    ]);
                }
            }

            // Update simple product variant
            if ($validated['product_type'] === 'simple') {
                $price = $validated['price'] ?? 0;
                $stock = $validated['stock'] ?? 0;

                $defaultVariant = $product->variants()->first();
                if ($defaultVariant) {
                    $defaultVariant->update([
                        'price' => $price,
                        'stock' => $stock,
                    ]);
                } else {
                    ProductVariant::create([
                        'product_id' => $product->product_id,
                        'sku'        => Str::slug($validated['slug']) . '-default',
                        'price'      => $price,
                        'stock'      => $stock,
                        'status'     => 'active',
                    ]);
                }

                $product->update([
                    'min_price' => $price,
                    'max_price' => $price,
                ]);
            } else {
                $variantsData = $request->input('variants', []);
                $allPrices = [];
                
                foreach ($variantsData as $vKey => $vData) {
                    if (isset($vData['variant_id'])) {
                        $variant = ProductVariant::find($vData['variant_id']);
                        if ($variant && $variant->product_id === $product->product_id) {
                            $vPrice = $vData['price'] ?? $variant->price;
                            
                            $variantUpdates = [
                                'price' => $vPrice,
                                'stock' => $vData['stock'] ?? $variant->stock,
                                'status' => $vData['status'] ?? $variant->status,
                            ];

                            // Nếu có upload ảnh mới cho biến thể
                            $variantFiles = $request->file("variants");
                            if (!empty($variantFiles) && isset($variantFiles[$vKey]['image'])) {
                                $vFile = $variantFiles[$vKey]['image'];
                            } else {
                                $vFile = null;
                            }

                            if ($vFile) {
                                if ($variant->image_url) {
                                    Storage::disk('public')->delete($variant->image_url);
                                }
                                $imgPath = $vFile->store('products/variants', 'public');
                                $variantUpdates['image_url'] = $imgPath;
                            } elseif (isset($vData['remove_image']) && $vData['remove_image']) {
                                if ($variant->image_url) {
                                    Storage::disk('public')->delete($variant->image_url);
                                }
                                $variantUpdates['image_url'] = null;
                            }

                            $variant->update($variantUpdates);
                            $allPrices[] = $vPrice;
                        }
                    }
                }
                
                // Cập nhật lại min max price dựa trên các variant đã cập nhật và các variant còn lại
                $allCurrentVariants = $product->variants()->get();
                $allPrices = [];
                foreach ($allCurrentVariants as $v) {
                    $allPrices[] = $v->price;
                }
                
                if (count($allPrices) > 0) {
                    $product->update([
                        'min_price' => min($allPrices),
                        'max_price' => max($allPrices),
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'Product updated successfully',
                'data'    => $product->load('variants', 'images'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update: ' . $e->getMessage(),
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
