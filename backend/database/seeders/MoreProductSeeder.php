<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MoreProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Fetch or Create Seller ID (Admin)
        $adminId = DB::table('users')->where('email', 'admin123@gmail.com')->value('user_id');
        if (!$adminId) {
            $adminId = DB::table('users')->insertGetId([
                'full_name' => 'Super Admin',
                'email' => 'admin123@gmail.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'admin',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Map old hardcoded IDs to Names
        $categoryNames = [
            6 => ['name' => 'Giày', 'parent' => null],
            7 => ['name' => 'Giày', 'parent' => null], 
            8 => ['name' => 'Vợt Yonex', 'parent' => 'Vợt'],
            9 => ['name' => 'Vợt Victor', 'parent' => 'Vợt'],
            10 => ['name' => 'Vợt Li-Ning', 'parent' => 'Vợt'],
            11 => ['name' => 'Áo cầu lông', 'parent' => 'Quần áo'],
            12 => ['name' => 'Quần cầu lông', 'parent' => 'Quần áo'],
            13 => ['name' => 'Váy cầu lông', 'parent' => 'Quần áo'],
            14 => ['name' => 'Balo & Túi', 'parent' => 'Phụ kiện'],
            15 => ['name' => 'Grip & Cuốn cán', 'parent' => 'Phụ kiện'],
            16 => ['name' => 'Cầu lông', 'parent' => 'Phụ kiện']
        ];

        $brandNames = [
            1 => 'Yonex',
            2 => 'Mizuno',
            3 => 'Li-Ning',
            4 => 'Victor'
        ];

        // 3. Helper to get or create Brand
        $getBrandId = function($name) use ($now) {
            $id = DB::table('brands')->where('name', $name)->value('brand_id');
            if (!$id) {
                $id = DB::table('brands')->insertGetId([
                    'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
                    'description' => "Thương hiệu $name", 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now
                ]);
            }
            return $id;
        };

        // 4. Helper to get or create Category
        $getCategoryId = function($name, $parentName = null) use ($now) {
            $parentId = null;
            if ($parentName) {
                $parentId = DB::table('categories')->where('name', $parentName)->value('category_id');
                if (!$parentId) {
                    $parentId = DB::table('categories')->insertGetId([
                        'name' => $parentName, 'slug' => \Illuminate\Support\Str::slug($parentName),
                        'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                    ]);
                }
            }
            
            $id = DB::table('categories')->where('name', $name)->value('category_id');
            if (!$id) {
                $id = DB::table('categories')->insertGetId([
                    'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'parent_id' => $parentId,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now
                ]);
            }
            return $id;
        };

        $baseProducts = [
            ['cat_old' => 8, 'brand_old' => 1, 'name' => 'Vợt Yonex Astrox 88D Pro', 'slug' => 'vot-yonex-astrox-88d-pro', 'price' => 3690000],
            ['cat_old' => 8, 'brand_old' => 1, 'name' => 'Vợt Yonex Astrox 88S Pro', 'slug' => 'vot-yonex-astrox-88s-pro', 'price' => 3690000],
            ['cat_old' => 9, 'brand_old' => 4, 'name' => 'Vợt Victor Jetspeed S 12F', 'slug' => 'vot-victor-jetspeed-s12f', 'price' => 2990000],
            ['cat_old' => 10, 'brand_old' => 3, 'name' => 'Vợt Li-Ning Halbertec 9000', 'slug' => 'vot-li-ning-halbertec-9000', 'price' => 3490000],
            ['cat_old' => 6, 'brand_old' => 1, 'name' => 'Giày Yonex Aerus Z2', 'slug' => 'giay-yonex-aerus-z2', 'price' => 3390000],
            ['cat_old' => 11, 'brand_old' => 1, 'name' => 'Áo Yonex 10566 Game Shirt', 'slug' => 'ao-yonex-10566-game', 'price' => 690000],
            ['cat_old' => 14, 'brand_old' => 3, 'name' => 'Túi Li-Ning ABJT015', 'slug' => 'tui-li-ning-abjt015', 'price' => 2290000],
        ];

        $targetCount = 500;
        $baseCount = count($baseProducts);

        for ($i = 1; $i <= $targetCount; $i++) {
            $data = $baseProducts[($i - 1) % $baseCount];
            
            $catInfo = $categoryNames[$data['cat_old']];
            $categoryId = $getCategoryId($catInfo['name'], $catInfo['parent']);
            $brandId = $getBrandId($brandNames[$data['brand_old']]);

            $productId = DB::table('products')->insertGetId([
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'seller_id' => $adminId,
                'name' => $data['name'] . " #" . $i,
                'slug' => $data['slug'] . "-" . $i,
                'short_description' => "Mô tả ngắn cho sản phẩm " . $data['name'],
                'min_price' => $data['price'],
                'max_price' => $data['price'],
                'product_type' => 'simple',
                'status' => 'active',
                'is_featured' => ($i % 10 == 0),
                'rating_avg' => round(mt_rand(35, 50) / 10, 1),
                'rating_count' => mt_rand(5, 100),
                'view_count' => mt_rand(10, 2000),
                'sold_count' => mt_rand(1, 500),
                'thumbnail_url' => 'products/thumbnails/product_placeholder.jpg',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('product_variants')->insert([
                'product_id' => $productId,
                'sku' => 'SKU-' . strtoupper(\Illuminate\Support\Str::random(5)) . "-" . $i,
                'variant_name' => 'Default',
                'price' => $data['price'],
                'cost_price' => $data['price'] * 0.7,
                'stock' => mt_rand(10, 100),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "HOÀN TẤT: Đã tạo 500 sản phẩm với đầy đủ Danh mục & Thương hiệu tự động!\n";
    }
}
