<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    // BUG 1: N+1 Query - loop query trong foreach
    public function courseStats(): JsonResponse
    {
        $courses = Course::all();
        $stats = [];

        foreach ($courses as $course) {
            // N+1: Mỗi vòng lặp thực hiện 1 query riêng
            $enrollmentCount = Enrollment::where('course_id', $course->id)->count();
            $activeStudents = Enrollment::where('course_id', $course->id)
                ->where('status', 'active')
                ->count();
            $completedStudents = Enrollment::where('course_id', $course->id)
                ->where('status', 'completed')
                ->count();
            $averageProgress = Enrollment::where('course_id', $course->id)
                ->avg('progress');

            // N+1: Load instructor riêng cho mỗi course
            $instructor = User::find($course->instructor_id);

            // N+1: Load category riêng
            $category = DB::table('categories')->where('id', $course->category_id)->first();

            $stats[] = [
                'course_id' => $course->id,
                'title' => $course->title,
                'instructor_name' => $instructor ? $instructor->name : 'Unknown',
                'category_name' => $category ? $category->name : 'Unknown',
                'total_enrollments' => $enrollmentCount,
                'active_students' => $activeStudents,
                'completed_students' => $completedStudents,
                'average_progress' => round($averageProgress ?? 0, 2),
                'revenue' => $enrollmentCount * $course->price,
            ];
        }

        return response()->json(['stats' => $stats]);
    }

    // BUG 4: Query trong loop không cache
    public function userStats(): JsonResponse
    {
        $users = User::all();
        $stats = [];

        foreach ($users as $user) {
            // Query trong loop - không cache, không eager load
            $enrollments = Enrollment::where('user_id', $user->id)->get();
            $completedCourses = Enrollment::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();

            $totalSpent = 0;
            foreach ($enrollments as $enrollment) {
                // N+1 lồng nhau: query course cho mỗi enrollment
                $course = Course::find($enrollment->course_id);
                if ($course) {
                    $totalSpent += $course->price;
                }
            }

            $stats[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'total_enrollments' => $enrollments->count(),
                'completed_courses' => $completedCourses,
                'total_spent' => $totalSpent,
            ];
        }

        return response()->json(['stats' => $stats]);
    }

    public function dashboard(): JsonResponse
    {
        // Query tất cả không phân trang
        $allCourses = Course::all();
        $allUsers = User::all();
        $allEnrollments = Enrollment::all();

        $categoryStats = [];
        $categories = DB::table('categories')->get();

        foreach ($categories as $category) {
            $courses = Course::where('category_id', $category->id)->get();
            $totalEnrollments = 0;
            $totalRevenue = 0;

            foreach ($courses as $course) {
                // Query trong loop lồng loop
                $count = Enrollment::where('course_id', $course->id)->count();
                $totalEnrollments += $count;
                $totalRevenue += $count * $course->price;
            }

            $categoryStats[] = [
                'category' => $category->name,
                'courses_count' => $courses->count(),
                'enrollments' => $totalEnrollments,
                'revenue' => $totalRevenue,
            ];
        }

        return response()->json([
            'total_courses' => $allCourses->count(),
            'total_users' => $allUsers->count(),
            'total_enrollments' => $allEnrollments->count(),
            'category_stats' => $categoryStats,
        ]);
    }
}
