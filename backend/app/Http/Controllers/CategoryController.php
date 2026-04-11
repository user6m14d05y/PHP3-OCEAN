<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;


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
        $categories = Cache::rememberForever('categories:tree', function () {
            $cats = Category::all()->toArray(); // Chuyển sang mảng
            return $this->buildTree($cats); // Gọi phương thức nội bộ
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi xác thực dữ liệu',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        // Nếu parent_id = 0, treat như null (danh mục gốc)
        if (isset($data['parent_id']) && $data['parent_id'] == 0) {
            $data['parent_id'] = null;
        }
        $data['slug'] = Str::slug($request->name);
        
        $originalSlug = $data['slug'];
        $count = 1;
        while (Category::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $count++;
        }

        $category = Category::create($data);
        Cache::forget('categories:tree');

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo danh mục thành công',
            'data' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi xác thực dữ liệu',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        // Nếu parent_id = 0, treat như null (danh mục gốc)
        if (isset($data['parent_id']) && $data['parent_id'] == 0) {
            $data['parent_id'] = null;
        }
        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->name);
            
            // Kiểm tra trùng slug (trừ chính nó)
            $originalSlug = $data['slug'];
            $count = 1;
            while (Category::where('slug', $data['slug'])->where('category_id', '!=', $id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $count++;
            }
        }

        $category->update($data);
        Cache::forget('categories:tree');

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật danh mục thành công',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }


        $hasChildren = Category::where('parent_id', $id)->exists();
        if ($hasChildren) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa danh mục có danh mục con'
            ], 400);
        }

        $category->delete();
        Cache::forget('categories:tree');

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa danh mục thành công'
        ]);
    }
}
