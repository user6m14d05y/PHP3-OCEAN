<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('category')->get();
        return response()->json($posts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'post_category_id' => 'required|exists:post_categories,post_category_id',
            'post_type' => 'nullable|string',
            'status' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'view_count' => 'nullable|integer',
            'published_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
        ]);
        $data = $request->all();
        $post = Post::where('slug', Str::slug($request->title))->first();
        if($post){
            $data['slug'] = Str::slug($request->title).'-'.rand(1, 100);
        }else{
            $data['slug'] = Str::slug($request->title);
        }
        $data['post_type'] = $request->post_type ?? 'news';
        $data['status'] = $request->status ?? 'draft';
        $data['is_featured'] = filter_var($request->is_featured ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['view_count'] = $request->view_count ?? 0;
        $data['published_at'] = $request->published_at ?? date('Y-m-d H:i:s');

        // Capture author if authenticated (Admin/Staff/User)
        $user = auth('admin')->user() ?? auth('api')->user();
        if ($user) {
            $data['author_id'] = clone $user->user_id ?? $user->admin_id ?? null;
        }

        if ($request->hasFile('thumbnail')) {
            $thumbnail = $request->file('thumbnail');
            $thumbnailPath = $thumbnail->store('uploads/posts', 'public');
            $data['thumbnail_url'] = 'storage/' . $thumbnailPath;
        }

        if ($request->hasFile('banner')) {
            $banner = $request->file('banner');
            $bannerPath = $banner->store('uploads/posts', 'public');
            $data['banner_url'] = 'storage/' . $bannerPath;
        }

        $newPost = Post::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm bài viết thành công',
            'data' => $newPost
        ]);
    }

    /**
     * Upload one image (Quill) to storage/public/uploads/posts
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ]);

        $path = $request->file('image')->store('uploads/posts', 'public');
        $url = asset('storage/' . $path);

        return response()->json([
            'status' => 'success',
            'url' => $url,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $post = Post::with('category')->findOrFail($id);
        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'post_category_id' => 'required|exists:post_categories,post_category_id',
            'post_type' => 'nullable|string',
            'status' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();

        // Xử lý slug
        if ($request->title !== $post->title) {
            $existingPost = Post::where('slug', Str::slug($request->title))->where('post_id', '!=', $id)->first();
            if($existingPost){
                $data['slug'] = Str::slug($request->title).'-'.rand(1, 100);
            }else{
                $data['slug'] = Str::slug($request->title);
            }
        }

        $data['is_featured'] = filter_var($request->is_featured ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('thumbnail')) {
            if ($post->thumbnail_url && Str::startsWith($post->thumbnail_url, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $post->thumbnail_url));
            }
            $thumbnailPath = $request->file('thumbnail')->store('uploads/posts', 'public');
            $data['thumbnail_url'] = 'storage/' . $thumbnailPath;
        }

        if ($request->hasFile('banner')) {
            if ($post->banner_url && Str::startsWith($post->banner_url, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $post->banner_url));
            }
            $bannerPath = $request->file('banner')->store('uploads/posts', 'public');
            $data['banner_url'] = 'storage/' . $bannerPath;
        }

        $post->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật bài viết thành công',
            'data' => $post
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        // Có thể thêm logic xóa ảnh tại đây nếu muốn xóa luôn khi soft delete
        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa bài viết thành công'
        ]);
    }
}
