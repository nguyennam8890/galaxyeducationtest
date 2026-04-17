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
    private const ALLOWED_TYPES = ['courses', 'users'];

    public function exportCsv(Request $request): JsonResponse
    {
        $type = $request->input('type', 'courses');

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return response()->json(['message' => 'Loại export không hợp lệ'], 422);
        }

        $exportDir = storage_path('exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = $exportDir . "/{$type}_" . date('Y-m-d') . '.csv';

        try {
            $handle = fopen($filename, 'w');
            if ($handle === false) {
                return response()->json(['message' => 'Không thể tạo file export'], 500);
            }

            if ($type === 'courses') {
                fputcsv($handle, ['ID', 'Title', 'Price', 'Status', 'Enrollments']);

                $courses = Course::all();
                foreach ($courses as $course) {
                    $count = Enrollment::where('course_id', $course->id)->count();
                    fputcsv($handle, [$course->id, $course->title, $course->price, $course->status, $count]);
                }
            } elseif ($type === 'users') {
                fputcsv($handle, ['ID', 'Name', 'Email', 'Created At']);

                $users = User::all();
                foreach ($users as $user) {
                    fputcsv($handle, [$user->id, $user->name, $user->email, $user->created_at]);
                }
            }

            fclose($handle);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Export thất bại: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Export thành công',
            'file' => "{$type}_" . date('Y-m-d') . '.csv',
        ]);
    }
}