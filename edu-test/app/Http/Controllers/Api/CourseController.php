<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['category', 'instructor'])
            ->withCount('enrollments');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('level')) {
            $query->byLevel($request->level);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $courses = $query->paginate($request->get('per_page', 15));

        return response()->json($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['numeric', 'min:0'],
            'level' => ['in:beginner,intermediate,advanced'],
            'status' => ['in:draft,published,archived'],
            'duration_hours' => ['integer', 'min:0'],
            'max_students' => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['instructor_id'] = $request->user()->id;

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Tạo khóa học thành công',
            'course' => $course->load(['category', 'instructor']),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load(['category', 'instructor', 'enrollments']);

        return response()->json([
            'course' => $course,
            'enrollment_count' => $course->enrollments->where('status', '!=', 'cancelled')->count(),
            'is_full' => $course->isFullyEnrolled(),
        ]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        if ($request->user()->id !== $course->instructor_id) {
            return response()->json([
                'message' => 'Bạn không có quyền chỉnh sửa khóa học này',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'price' => ['numeric', 'min:0'],
            'level' => ['in:beginner,intermediate,advanced'],
            'status' => ['in:draft,published,archived'],
            'duration_hours' => ['integer', 'min:0'],
            'max_students' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $course->update($validated);

        return response()->json([
            'message' => 'Cập nhật khóa học thành công',
            'course' => $course->fresh()->load(['category', 'instructor']),
        ]);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        if ($request->user()->id !== $course->instructor_id) {
            return response()->json([
                'message' => 'Bạn không có quyền xóa khóa học này',
            ], 403);
        }

        $activeEnrollments = $course->enrollments()->where('status', 'active')->count();

        if ($activeEnrollments > 0) {
            return response()->json([
                'message' => "Không thể xóa khóa học đang có {$activeEnrollments} học viên đang học",
            ], 422);
        }

        $course->delete();

        return response()->json([
            'message' => 'Xóa khóa học thành công',
        ]);
    }
}
