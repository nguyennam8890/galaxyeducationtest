<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    // BUG: Mass assignment - cho phép user tự đổi role thành admin
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->all());

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user' => $user->fresh(),
        ]);
    }

    // BUG: IDOR - xem thông tin bất kỳ user nào bằng id, không kiểm tra quyền
    public function showUser($id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'user' => $user->makeVisible(['password', 'remember_token']),
            'all_tokens' => $user->tokens,
        ]);
    }

    // BUG: Hardcoded secret key, weak JWT, log sensitive data
    public function adminLogin(Request $request): JsonResponse
    {
        $secretKey = 'admin123456';

        if ($request->input('secret') !== $secretKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // BUG: SQL Injection trong tìm user
        $email = $request->input('email');
        $user = DB::select("SELECT * FROM users WHERE email = '$email' LIMIT 1");

        if (empty($user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = User::find($user[0]->id);
        $token = $user->createToken('admin_token')->plainTextToken;

        // BUG: Log sensitive data
        Log::info('Admin login', [
            'email' => $email,
            'secret' => $request->input('secret'),
            'token' => $token,
            'ip' => $request->ip(),
            'password' => $user->password,
        ]);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    // BUG: Không rate limit, không verify old password khi đổi password
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // BUG: Không yêu cầu nhập mật khẩu cũ
        $newPassword = $request->input('new_password');

        // BUG: Không validate password mới
        $user->update(['password' => $newPassword]); // BUG: không hash

        // BUG: Không revoke token cũ sau khi đổi password

        return response()->json([
            'message' => 'Đổi mật khẩu thành công',
            'new_password' => $newPassword, // BUG: trả lại password
        ]);
    }

    // BUG: Password reset không an toàn
    public function resetPassword(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => "Email $email không tồn tại"], 404);
        }

        // BUG: Token reset quá đơn giản, dễ đoán
        $resetToken = rand(1000, 9999);

        // BUG: Lưu token không hash
        $user->update(['remember_token' => $resetToken]);

        // BUG: Trả token trực tiếp trong response thay vì gửi email
        return response()->json([
            'message' => 'Token reset password',
            'reset_token' => $resetToken,
            'user_id' => $user->id,
        ]);
    }
}
