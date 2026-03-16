<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;


class CategoryController extends Controller
{

    private function buildTree(array $elements, $parentId = 0)
    {
        $branch = [];
        foreach ($elements as $element) {
            $idKey = array_key_exists('category_id', $element) ? 'category_id' : 'id';

            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element[$idKey]);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all()->toArray(); // Chuyển sang mảng
        $categories = $this->buildTree($categories); // Gọi phương thức nội bộ
        return response()->json($categories);
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
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
