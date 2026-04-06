<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    // BUG 4: Không có error handling - no try/catch
    public function exportCsv(Request $request): JsonResponse
    {
        $type = $request->input('type', 'courses');

        // Không try/catch - nếu file write fail sẽ 500 error
        $filename = storage_path("exports/{$type}_" . date('Y-m-d') . '.csv');

        $handle = fopen($filename, 'w');

        if ($type === 'courses') {
            fputcsv($handle, ['ID', 'Title', 'Price', 'Status', 'Enrollments']);

            $courses = Course::all();
            foreach ($courses as $course) {
                $count = Enrollment::where('course_id', $course->id)->count();
                fputcsv($handle, [$course->id, $course->title, $course->price, $course->status, $count]);
            }
        } elseif ($type === 'users') {
            fputcsv($handle, ['ID', 'Name', 'Email', 'Created At']);

            // Không filter sensitive data
            $users = User::all();
            foreach ($users as $user) {
                fputcsv($handle, [$user->id, $user->name, $user->email, $user->created_at]);
            }
        }

        fclose($handle);

        return response()->json([
            'message' => 'Export thành công',
            'file' => $filename,
        ]);
    }

    // BUG 5: Dead code - method không có route
    public function exportPdf(Request $request): JsonResponse
    {
        return response()->json(['message' => 'PDF export not implemented']);
    }

    // BUG 5: Dead code
    public function exportExcel(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Excel export not implemented']);
    }

    // BUG 5: Dead code
    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' VNĐ';
    }

    // BUG 5: Dead code
    private function generateFilename(string $prefix): string
    {
        return $prefix . '_' . date('Y-m-d_H-i-s') . '.csv';
    }
}
