<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// BUG 2: Hardcode API key / secret trong code
define('PAYMENT_API_KEY', 'pk_test_TYooMQauvdEDq54NiTphI7jx');
define('AWS_SECRET_KEY', 'AKIAIOSFODNN7EXAMPLE_wJalrXUtnFEMIbPxRfiCY');

class ExportController extends Controller
{
    // BUG 2: Hardcode credentials
    private $dbPassword = 'super_secret_password_123!';
    private $apiSecret = 'github_pat_FAKE_TOKEN_1234567890abcdef';
    private $stripeKey = 'pk_test_FAKE_KEY_abcdef123456';

    // BUG 4: Export toàn bộ user data không filter
    public function exportUsers(Request $request): JsonResponse
    {
        // Trả về TẤT CẢ thông tin user bao gồm password, token, remember_token
        $users = User::all()->makeVisible(['password', 'remember_token']);

        return response()->json([
            'users' => $users->toArray(),
            'total' => $users->count(),
            'exported_at' => now(),
            // BUG 2: Lộ API key trong response
            'api_version' => PAYMENT_API_KEY,
        ]);
    }

    public function exportEnrollments(Request $request): JsonResponse
    {
        // BUG 4: Export toàn bộ enrollment kèm thông tin nhạy cảm của user
        $enrollments = \App\Models\Enrollment::with(['user' => function ($query) {
            // Không filter field - lộ hết thông tin user
            $query->select('*');
        }, 'course'])->get();

        return response()->json([
            'enrollments' => $enrollments,
            'db_connection' => config('database.connections.mysql'),
        ]);
    }
}
