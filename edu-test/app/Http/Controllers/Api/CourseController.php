<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    // BUG 1: Không dùng Form Request, validate trực tiếp trong controller
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

    // BUG 1: Rất nhiều validation inline thay vì dùng Form Request
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

    // BUG 1: Lại validate inline
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

        $course->delete();

        return response()->json([
            'message' => 'Xóa khóa học thành công',
        ]);
    }

    // BUG 5: Dead code - method không được gọi từ route nào
    public function getPopularCourses(): JsonResponse
    {
        $courses = Course::withCount('enrollments')
            ->orderBy('enrollments_count', 'desc')
            ->take(10)
            ->get();

        return response()->json(['courses' => $courses]);
    }

    // BUG 5: Dead code
    public function getRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $enrolledCourseIds = Enrollment::where('user_id', $user->id)->pluck('course_id');
        $enrolledCategories = Course::whereIn('id', $enrolledCourseIds)->pluck('category_id');

        $recommendations = Course::whereIn('category_id', $enrolledCategories)
            ->whereNotIn('id', $enrolledCourseIds)
            ->where('status', 'published')
            ->take(5)
            ->get();

        return response()->json(['recommendations' => $recommendations]);
    }

    // BUG 5: Dead code
    private function calculateCourseScore(Course $course): float
    {
        $enrollmentScore = $course->enrollments()->count() * 0.5;
        $completionRate = $course->enrollments()->where('status', 'completed')->count() /
            max($course->enrollments()->count(), 1);
        return $enrollmentScore + ($completionRate * 100);
    }
}
