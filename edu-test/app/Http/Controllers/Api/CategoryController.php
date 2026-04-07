<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('courses')->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Tạo danh mục thành công',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['courses' => function ($query) {
            $query->where('status', 'published')->withCount('enrollments');
        }]);

        return response()->json([
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Cập nhật danh mục thành công',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->courses()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa danh mục đang có khóa học',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Xóa danh mục thành công',
        ]);
    }

    // BUG: XSS - render HTML từ user input không escape
    public function preview(Request $request): JsonResponse
    {
        $name = $request->input('name');
        $description = $request->input('description');

        // BUG: Stored XSS - trả về HTML không escape
        return response()->json([
            'preview_html' => "<div class='category-preview'><h2>{$name}</h2><p>{$description}</p></div>",
            'title_display' => "<title>{$name} - Galaxy Education</title>",
        ]);
    }

    // BUG: SQL Injection trong search
    public function search(Request $request): JsonResponse
    {
        $keyword = $request->input('q');
        $sortBy = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');

        // BUG: SQL Injection qua keyword, sortBy, và order
        $categories = DB::select("
            SELECT * FROM categories
            WHERE name LIKE '%{$keyword}%' OR description LIKE '%{$keyword}%'
            ORDER BY {$sortBy} {$order}
        ");

        return response()->json([
            'results' => $categories,
            'query' => $keyword, // BUG: Reflected XSS
        ]);
    }

    // BUG: Không check quyền, mass delete
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        // BUG: Không validate, không check quyền, xóa luôn cả category có course
        Category::whereIn('id', $ids)->forceDelete();

        return response()->json([
            'message' => "Đã xóa " . count($ids) . " danh mục",
        ]);
    }

    // BUG: Open redirect
    public function redirect(Request $request): \Illuminate\Http\RedirectResponse
    {
        $url = $request->input('url');

        // BUG: Open redirect - không validate URL
        return redirect($url);
    }
}
