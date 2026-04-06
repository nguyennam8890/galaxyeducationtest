<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    // BUG 3: God method - 1 method quá dài làm quá nhiều thứ
    public function generateReport(Request $request): JsonResponse
    {
        // ===== PHẦN 1: Thống kê tổng quan =====
        $totalUsers = User::count();
        $totalCourses = Course::count();
        $totalEnrollments = Enrollment::count();
        $totalRevenue = 0;

        $courses = Course::all();
        foreach ($courses as $course) {
            $enrollmentCount = Enrollment::where('course_id', $course->id)
                ->where('status', '!=', 'cancelled')
                ->count();
            $totalRevenue += $enrollmentCount * $course->price;
        }

        // ===== PHẦN 2: Thống kê theo category =====
        $categories = Category::all();
        $categoryStats = [];
        foreach ($categories as $category) {
            $categoryCourses = Course::where('category_id', $category->id)->get();
            $categoryEnrollments = 0;
            $categoryRevenue = 0;
            $categoryCompletions = 0;

            foreach ($categoryCourses as $course) {
                $enrollments = Enrollment::where('course_id', $course->id)->get();
                $categoryEnrollments += $enrollments->count();
                $categoryRevenue += $enrollments->where('status', '!=', 'cancelled')->count() * $course->price;
                $categoryCompletions += $enrollments->where('status', 'completed')->count();
            }

            $categoryStats[] = [
                'id' => $category->id,
                'name' => $category->name,
                'courses_count' => $categoryCourses->count(),
                'enrollments' => $categoryEnrollments,
                'revenue' => $categoryRevenue,
                'completions' => $categoryCompletions,
                'completion_rate' => $categoryEnrollments > 0
                    ? round(($categoryCompletions / $categoryEnrollments) * 100, 2)
                    : 0,
            ];
        }

        // ===== PHẦN 3: Thống kê theo instructor =====
        $instructors = User::has('instructedCourses')->get();
        $instructorStats = [];
        foreach ($instructors as $instructor) {
            $instructorCourses = Course::where('instructor_id', $instructor->id)->get();
            $instructorRevenue = 0;
            $instructorStudents = 0;

            foreach ($instructorCourses as $course) {
                $courseEnrollments = Enrollment::where('course_id', $course->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();
                $instructorRevenue += $courseEnrollments * $course->price;
                $instructorStudents += $courseEnrollments;
            }

            $instructorStats[] = [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'courses_count' => $instructorCourses->count(),
                'total_students' => $instructorStudents,
                'total_revenue' => $instructorRevenue,
                'avg_students_per_course' => $instructorCourses->count() > 0
                    ? round($instructorStudents / $instructorCourses->count(), 1)
                    : 0,
            ];
        }

        // ===== PHẦN 4: Thống kê theo thời gian =====
        $monthlyStats = [];
        for ($i = 11; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();

            $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
            $newEnrollments = Enrollment::whereBetween('created_at', [$startDate, $endDate])->count();
            $completions = Enrollment::where('status', 'completed')
                ->whereBetween('completed_at', [$startDate, $endDate])
                ->count();
            $newCourses = Course::whereBetween('created_at', [$startDate, $endDate])->count();

            $monthlyRevenue = 0;
            $monthEnrollments = Enrollment::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', '!=', 'cancelled')
                ->get();
            foreach ($monthEnrollments as $enrollment) {
                $course = Course::find($enrollment->course_id);
                if ($course) {
                    $monthlyRevenue += $course->price;
                }
            }

            $monthlyStats[] = [
                'month' => $startDate->format('Y-m'),
                'new_users' => $newUsers,
                'new_enrollments' => $newEnrollments,
                'completions' => $completions,
                'new_courses' => $newCourses,
                'revenue' => $monthlyRevenue,
            ];
        }

        // ===== PHẦN 5: Top courses =====
        $topCourses = [];
        foreach ($courses as $course) {
            $enrollmentCount = Enrollment::where('course_id', $course->id)
                ->where('status', '!=', 'cancelled')
                ->count();
            $completedCount = Enrollment::where('course_id', $course->id)
                ->where('status', 'completed')
                ->count();
            $avgProgress = Enrollment::where('course_id', $course->id)
                ->where('status', 'active')
                ->avg('progress');

            $topCourses[] = [
                'id' => $course->id,
                'title' => $course->title,
                'enrollments' => $enrollmentCount,
                'completions' => $completedCount,
                'avg_progress' => round($avgProgress ?? 0, 1),
                'revenue' => $enrollmentCount * $course->price,
                'completion_rate' => $enrollmentCount > 0
                    ? round(($completedCount / $enrollmentCount) * 100, 1)
                    : 0,
            ];
        }

        usort($topCourses, function ($a, $b) {
            return $b['enrollments'] - $a['enrollments'];
        });

        // ===== PHẦN 6: User engagement =====
        $activeUsers = User::whereHas('enrollments', function ($q) {
            $q->where('status', 'active');
        })->count();

        $avgCoursesPerUser = $totalUsers > 0
            ? round(Enrollment::where('status', '!=', 'cancelled')->count() / $totalUsers, 1)
            : 0;

        // ===== TRẢ KẾT QUẢ =====
        return response()->json([
            'overview' => [
                'total_users' => $totalUsers,
                'total_courses' => $totalCourses,
                'total_enrollments' => $totalEnrollments,
                'total_revenue' => $totalRevenue,
                'active_users' => $activeUsers,
                'avg_courses_per_user' => $avgCoursesPerUser,
            ],
            'category_stats' => $categoryStats,
            'instructor_stats' => $instructorStats,
            'monthly_stats' => $monthlyStats,
            'top_courses' => array_slice($topCourses, 0, 10),
        ]);
    }
}
