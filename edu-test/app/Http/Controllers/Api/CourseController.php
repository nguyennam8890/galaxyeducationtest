<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // BUG 7: SQL Injection - dùng raw query với input user không sanitize
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereRaw("title LIKE '%" . $search . "%' OR description LIKE '%" . $search . "%'");
        }

        // BUG 8: Cho phép user set per_page không giới hạn -> memory exhaustion
        $courses = $query->paginate($request->get('per_page', 100000));

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

    // BUG 9: Không kiểm tra quyền - ai cũng xóa được khóa học của người khác
    public function destroy(Request $request, Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Xóa khóa học thành công',
        ]);
    }

    // BUG: Command Injection - dùng shell_exec với input user
    public function exportCourses(Request $request): JsonResponse
    {
        $format = $request->input('format', 'csv');
        $filename = $request->input('filename', 'courses');

        // BUG: Command injection qua filename và format
        $output = shell_exec("php artisan export:courses --format={$format} --output=/tmp/{$filename}.{$format}");

        return response()->json([
            'message' => 'Export thành công',
            'output' => $output,
            'download_url' => url("/tmp/{$filename}.{$format}"),
        ]);
    }

    // BUG: Unsafe deserialization
    public function importCourses(Request $request): JsonResponse
    {
        $data = $request->input('data');

        // BUG: Unsafe unserialize - có thể dẫn đến RCE
        $courses = unserialize(base64_decode($data));

        foreach ($courses as $courseData) {
            Course::create($courseData);
        }

        return response()->json([
            'message' => 'Import thành công',
            'count' => count($courses),
        ]);
    }

    // BUG: SSRF - fetch URL từ user input
    public function fetchThumbnail(Request $request): JsonResponse
    {
        $url = $request->input('url');

        // BUG: SSRF - không validate URL, có thể truy cập internal services
        $content = file_get_contents($url);
        $base64 = base64_encode($content);

        return response()->json([
            'thumbnail' => "data:image/png;base64,{$base64}",
            'source_url' => $url,
            'size' => strlen($content),
        ]);
    }

    // BUG: Race condition + Mass Assignment trong bulk update
    public function bulkUpdate(Request $request): JsonResponse
    {
        $updates = $request->input('courses', []);

        // BUG: Không dùng transaction, không validate, không check quyền
        foreach ($updates as $update) {
            $course = Course::find($update['id']);
            if ($course) {
                // BUG: Mass assignment - có thể đổi instructor_id
                $course->update($update);
            }
        }

        return response()->json([
            'message' => "Cập nhật {$count} khóa học thành công",
        ]);
    }

    // BUG: Lộ thông tin nhạy cảm qua debug endpoint
    public function debug(Request $request): JsonResponse
    {
        return response()->json([
            'env' => [
                'APP_KEY' => env('APP_KEY'),
                'DB_PASSWORD' => env('DB_PASSWORD'),
                'DB_HOST' => env('DB_HOST'),
                'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
                'AWS_SECRET' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'server' => $_SERVER,
            'php_version' => phpversion(),
            'loaded_extensions' => get_loaded_extensions(),
            'db_tables' => DB::select('SHOW TABLES'),
        ]);
    }
}
