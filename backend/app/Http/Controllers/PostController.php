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
        $posts = Post::with('category')->orderBy('post_id', 'desc')->get();
        return response()->json($posts);
    }

    /**
     * Lấy chi tiết bài viết theo ID (admin edit)
     */
    public function edit($id)
    {
        $post = Post::with('category')->findOrFail($id);
        return response()->json($post);
    }

    /**
     * Tạo bài viết mới
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
            'published_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
        ]);

        // Tạo slug unique
        $slug = Str::slug($request->title);
        if (Post::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . rand(1, 100);
        }

        // Upload ảnh
        $thumbnailUrl = null;
        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('uploads/posts', 'public');
            $thumbnailUrl = 'storage/' . $path;
        }

        $bannerUrl = null;
        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('uploads/posts', 'public');
            $bannerUrl = 'storage/' . $path;
        }

        $post = Post::create([
            'post_category_id' => $request->post_category_id,
            'title' => $request->title,
            'slug' => $slug,
            'summary' => $request->summary,
            'content' => $request->content,
            'thumbnail_url' => $thumbnailUrl,
            'banner_url' => $bannerUrl,
            'post_type' => $request->post_type ?? 'news',
            'status' => $request->status ?? 'draft',
            'is_featured' => $request->is_featured ?? false,
            'published_at' => $request->published_at ?? now(),
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm bài viết thành công',
            'data' => $post,
        ]);
    }

    /**
     * Cập nhật bài viết
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
            'is_featured' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
        ]);

        $data = [
            'post_category_id' => $request->post_category_id,
            'title' => $request->title,
            'slug' => $request->slug ?: Str::slug($request->title),
            'summary' => $request->summary,
            'content' => $request->content,
            'post_type' => $request->post_type ?? $post->post_type,
            'status' => $request->status ?? $post->status,
            'is_featured' => $request->is_featured ?? $post->is_featured,
            'published_at' => $request->published_at ?? $post->published_at,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
        ];

        // Upload ảnh mới nếu có
        if ($request->hasFile('thumbnail')) {
            // Xóa ảnh cũ
            if ($post->getRawOriginal('thumbnail_url')) {
                $oldPath = str_replace('storage/', '', $post->getRawOriginal('thumbnail_url'));
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('thumbnail')->store('uploads/posts', 'public');
            $data['thumbnail_url'] = 'storage/' . $path;
        }

        if ($request->hasFile('banner')) {
            if ($post->getRawOriginal('banner_url')) {
                $oldPath = str_replace('storage/', '', $post->getRawOriginal('banner_url'));
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('banner')->store('uploads/posts', 'public');
            $data['banner_url'] = 'storage/' . $path;
        }

        $post->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật bài viết thành công',
            'data' => $post->fresh()->load('category'),
        ]);
    }

    /**
     * Xóa bài viết
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa bài viết thành công',
        ]);
    }
}
