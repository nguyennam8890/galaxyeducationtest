<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    // BUG 1: Không validate file type - cho phép upload .php, .exe, .sh
    // BUG 2: Không giới hạn file size
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            // BUG: Không validate mime type, không giới hạn size
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');

        // BUG 3: Path traversal - dùng filename gốc từ user, có thể chứa ../
        $filename = $request->input('filename', $file->getClientOriginalName());

        // BUG 4: Lưu file vào public folder - ai cũng truy cập được
        $path = $file->move(public_path('uploads'), $filename);

        return response()->json([
            'message' => 'Upload thành công',
            'path' => '/uploads/' . $filename,
            'url' => url('/uploads/' . $filename),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    // BUG: Upload avatar không validate
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            // BUG 1: Không validate là image, chấp nhận mọi file type
            // BUG 2: Không giới hạn size
            'avatar' => ['required', 'file'],
        ]);

        $file = $request->file('avatar');

        // BUG 3: Path traversal - dùng user input làm tên file
        $customName = $request->input('name', $file->getClientOriginalName());

        // BUG 4: Lưu vào public path
        $file->move(public_path('avatars'), $customName);

        return response()->json([
            'message' => 'Upload avatar thành công',
            'avatar_url' => url('/avatars/' . $customName),
        ]);
    }

    // BUG: Upload course material không an toàn
    public function uploadMaterial(Request $request, $courseId): JsonResponse
    {
        $request->validate([
            // BUG: Chấp nh���n nhiều file, không validate type hay size
            'files' => ['required', 'array'],
            'files.*' => ['file'],
        ]);

        $uploadedFiles = [];

        foreach ($request->file('files') as $file) {
            // BUG 3: Không sanitize filename - path traversal
            $originalName = $file->getClientOriginalName();

            // BUG 4: Lưu vào public folder
            $file->move(public_path("materials/course_{$courseId}"), $originalName);

            $uploadedFiles[] = [
                'name' => $originalName,
                'url' => url("/materials/course_{$courseId}/{$originalName}"),
                'size' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
            ];
        }

        return response()->json([
            'message' => 'Upload tài liệu thành công',
            'files' => $uploadedFiles,
        ]);
    }

    // BUG: Download không kiểm tra authorization
    public function download(Request $request): JsonResponse
    {
        // BUG 3: Path traversal qua filename parameter
        $filename = $request->input('filename');
        $filePath = public_path('uploads/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File không tồn tại'], 404);
        }

        return response()->json([
            'download_url' => url('/uploads/' . $filename),
            'file_info' => [
                'size' => filesize($filePath),
                'path' => $filePath, // BUG: Lộ server path
            ],
        ]);
    }
}
