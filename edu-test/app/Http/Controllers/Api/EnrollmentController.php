<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with('course.category')
            ->get();

        return response()->json([
            'enrollments' => $enrollments,
        ]);
    }

    public function enroll(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        // Kiểm tra khóa học đã publish chưa
        if ($course->status !== 'published') {
            return response()->json([
                'message' => 'Khóa học chưa được mở đăng ký',
            ], 422);
        }

        // Kiểm tra đã đăng ký chưa
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existingEnrollment) {
            if ($existingEnrollment->status === 'cancelled') {
                $existingEnrollment->update(['status' => 'active', 'progress' => 0]);

                return response()->json([
                    'message' => 'Đăng ký lại khóa học thành công',
                    'enrollment' => $existingEnrollment->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Bạn đã đăng ký khóa học này rồi',
            ], 422);
        }

        // Kiểm tra khóa học đã đầy chưa
        if ($course->isFullyEnrolled()) {
            return response()->json([
                'message' => 'Khóa học đã đầy, không thể đăng ký thêm',
            ], 422);
        }

        // Không cho giảng viên tự đăng ký khóa học của mình
        if ($user->id === $course->instructor_id) {
            return response()->json([
                'message' => 'Giảng viên không thể đăng ký khóa học của mình',
            ], 422);
        }

        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        return response()->json([
            'message' => 'Đăng ký khóa học thành công',
            'enrollment' => $enrollment->load('course'),
        ], 201);
    }

    public function cancel(Request $request, Course $course): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'Không tìm thấy đăng ký khóa học này',
            ], 404);
        }

        $enrollment->cancel();

        return response()->json([
            'message' => 'Hủy đăng ký khóa học thành công',
        ]);
    }

    // BUG 10: Cho phép user cập nhật progress của BẤT KỲ ai, không check user_id
    public function updateProgress(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:-100', 'max:999'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $targetUserId = $request->input('user_id', $request->user()->id);

        $enrollment = Enrollment::where('user_id', $targetUserId)
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'Bạn chưa đăng ký khóa học này',
            ], 404);
        }

        $enrollment->update(['progress' => $validated['progress']]);

        if ($validated['progress'] >= 100) {
            $enrollment->markAsCompleted();
        }

        return response()->json([
            'message' => 'Cập nhật tiến độ thành công',
            'enrollment' => $enrollment->fresh(),
        ]);
    }
}
