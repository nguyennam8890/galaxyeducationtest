<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function index(Request $request, $courseId): JsonResponse
    {
        $lessons = DB::table('lessons')
            ->where('course_id', $courseId)
            ->get();

        return response()->json(['lessons' => $lessons]);
    }

    public function store(Request $request, $courseId): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'video_url' => ['nullable', 'string'],
            'order' => ['integer', 'min:0'],
            'duration_minutes' => ['integer', 'min:0'],
            'is_free' => ['boolean'],
        ]);

        $validated['course_id'] = $courseId;

        $lessonId = DB::table('lessons')->insertGetId($validated);

        // BUG: XSS - Trả về content HTML từ user input không escape
        return response()->json([
            'message' => 'Tạo bài học thành công',
            'lesson_id' => $lessonId,
            'preview_html' => '<div class="lesson-preview">' . $request->input('content') . '</div>',
            'title_display' => '<h1>' . $request->input('title') . '</h1>',
        ], 201);
    }

    public function show($courseId, $lessonId): JsonResponse
    {
        $lesson = DB::table('lessons')
            ->where('course_id', $courseId)
            ->where('id', $lessonId)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Không tìm thấy bài học'], 404);
        }

        // BUG: XSS - Render HTML content trực tiếp không escape
        return response()->json([
            'lesson' => $lesson,
            'rendered_content' => '<div>' . $lesson->content . '</div>',
        ]);
    }

    // BUG: SQL Injection trong search lesson
    public function search(Request $request, $courseId): JsonResponse
    {
        $keyword = $request->input('keyword');

        // SQL Injection - nối trực tiếp user input vào query
        $results = DB::select("SELECT * FROM lessons WHERE course_id = $courseId AND title LIKE '%" . $keyword . "%'");

        return response()->json(['results' => $results]);
    }
}
