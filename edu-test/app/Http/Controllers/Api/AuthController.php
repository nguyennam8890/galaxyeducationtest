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
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // BUG 3: Log sensitive data (password, token)
        Log::info('User registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'password' => $validated['password'],
            'ip' => $request->ip(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // BUG 3: Log token
        Log::info('Token created for user: ' . $user->email . ', token: ' . $token);

        // BUG 1: Trả password trong API response
        return response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user->makeVisible(['password']),
            'token' => $token,
            'debug_info' => [
                'raw_password' => $validated['password'],
                'hashed_password' => $user->password,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($validated)) {
            // BUG 3: Log failed login attempt with password
            Log::warning('Failed login attempt', [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Thông tin đăng nhập không đúng'], 401);
        }

        $user = User::where('email', $validated['email'])->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        // BUG 3: Log password on successful login
        Log::info('User logged in', [
            'user_id' => $user->id,
            'password_used' => $validated['password'],
            'token' => $token,
        ]);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['enrollments.course', 'instructedCourses']),
        ]);
    }
}
