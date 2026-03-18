<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 12);
        $offset = ($page - 1) * $limit;
        $products = Product::with([
            'mainImage' => function ($query) {
                $query->select('product_images.image_id', 'product_images.image_url', 'product_images.product_id');
            },
            'lowestPriceVariant' => function ($query) {
                $query->select('product_variants.variant_id', 'product_variants.price', 'product_variants.stock', 'product_variants.product_id');
            }
        ])
            ->select('product_id', 'name', 'category_id', 'slug')
            ->offset($offset)
            ->limit($limit)
            ->get();
        return response()->json($products);
    }
    public function show($slug)
    {
        $product = Product::with([
            'category',
            'images',
            'variants'
        ])
            ->where('slug', $slug)
            ->first();
        return response()->json($product);
    }

    public function all(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 12);
        $offset = ($page - 1) * $limit;
        $products = Product::with([
            'mainImage' => function ($query) {
                $query->select('product_images.image_id', 'product_images.image_url', 'product_images.product_id');
            },
            'lowestPriceVariant' => function ($query) {
                $query->select('product_variants.variant_id', 'product_variants.price', 'product_variants.stock', 'product_variants.product_id');
            }
        ])
            ->offset($offset)
            ->limit($limit)
            ->get();
        return response()->json($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
