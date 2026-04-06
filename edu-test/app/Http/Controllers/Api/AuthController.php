<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // BUG 1: Lưu password plain text, không hash
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        // BUG: Lưu password plain text - KHÔNG dùng Hash::make()
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // BUG 2: So sánh password bằng == thay vì Hash::check
    // BUG 3: User enumeration - tiết lộ email tồn tại
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // BUG: User enumeration - response khác nhau khi email không tồn tại vs sai password
        if (!$user) {
            return response()->json([
                'message' => 'Email này chưa được đăng ký trong hệ thống',
            ], 401);
        }

        // BUG: So sánh password bằng == (loose comparison), không dùng Hash::check()
        if ($user->password == $validated['password']) {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Đăng nhập thành công',
                'user' => $user,
                'token' => $token,
            ]);
        }

        return response()->json([
            'message' => 'Mật khẩu không chính xác',
        ], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công',
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['enrollments.course', 'instructedCourses']),
        ]);
    }

    // BUG: Endpoint reset password không verify token đúng cách
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        // BUG: Cho phép reset password mà không cần verify email/token
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Email không tồn tại'], 404);
        }

        // BUG: Vẫn lưu plain text
        $user->update(['password' => $validated['new_password']]);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }
}
