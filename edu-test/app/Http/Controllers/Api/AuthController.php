<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // BUG 1: Không validate password đủ mạnh, chỉ cần 1 ký tự
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required'],
        ]);

        // BUG 2: Không hash password - lưu plain text vào DB
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // BUG 3: Trả về password trong response
        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user->makeVisible('password'),
            'token' => $token,
            'debug_password' => $validated['password'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        // BUG 4: Không validate input login
        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        // BUG 5: So sánh password plain text, lộ timing attack + không dùng Hash::check
        if (!$user || $user->password != $password) {
            // BUG 6: Tiết lộ user có tồn tại hay không (user enumeration)
            if (!$user) {
                return response()->json(['message' => 'Email không tồn tại trong hệ thống'], 401);
            }
            return response()->json(['message' => 'Mật khẩu không đúng'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token,
        ]);
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
}
