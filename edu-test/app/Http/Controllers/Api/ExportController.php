<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    // BUG: Command Injection - dùng exec() với user input không sanitize
    public function exportCourses(Request $request): JsonResponse
    {
        $format = $request->input('format', 'csv');
        $filename = $request->input('filename', 'courses_export');

        // Command Injection - user có thể inject command qua filename
        $outputPath = storage_path('exports/' . $filename . '.' . $format);
        exec("echo 'Exporting data...' > " . $outputPath, $output, $returnCode);

        // Command Injection thứ 2 - dùng shell_exec với user input
        $result = shell_exec("wc -l " . $outputPath);

        return response()->json([
            'message' => 'Export thành công',
            'file' => $outputPath,
            'lines' => $result,
        ]);
    }

    public function exportByDate(Request $request): JsonResponse
    {
        $date = $request->input('date');

        // Command Injection qua system()
        $command = "find /tmp/exports -name '*" . $date . "*' -type f";
        $files = [];
        exec($command, $files);

        return response()->json([
            'files' => $files,
        ]);
    }
}
