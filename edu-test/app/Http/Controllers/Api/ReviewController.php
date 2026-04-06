<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index($courseId): JsonResponse
    {
        $reviews = DB::table('reviews')
            ->where('course_id', $courseId)
            ->where('is_approved', true)
            ->join('users', 'reviews.user_id', '=', 'users.id')
            ->select('reviews.*', 'users.name as user_name')
            ->get();

        $avgRating = DB::table('reviews')
            ->where('course_id', $courseId)
            ->where('is_approved', true)
            ->avg('rating');

        return response()->json([
            'reviews' => $reviews,
            'average_rating' => round($avgRating ?? 0, 1),
            'total' => $reviews->count(),
        ]);
    }

    // BUG 1: Rating chấp nhận giá trị -5 đến 100 (đúng phải là 1-5)
    public function store(Request $request, $courseId): JsonResponse
    {
        $validated = $request->validate([
            // BUG: min:-5 và max:100 thay vì min:1 max:5
            'rating' => ['required', 'integer', 'min:-5', 'max:100'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $enrollment = DB::table('enrollments')
            ->where('user_id', $request->user()->id)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'Bạn cần đăng ký khóa học trước khi đánh giá',
            ], 422);
        }

        // Không check xem user đã review chưa -> có thể review nhiều lần
        $reviewId = DB::table('reviews')->insertGetId([
            'user_id' => $request->user()->id,
            'course_id' => $courseId,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đánh giá thành công',
            'review_id' => $reviewId,
        ], 201);
    }
}
