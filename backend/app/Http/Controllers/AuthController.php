<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Đăng ký tài khoản mới.
     */
    public function register(Request $request)
    {
        $name = $request->input('full_name') ?? $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        // Kiểm tra email đã tồn tại chưa
        $checkEmail = DB::select("SELECT * FROM users WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422);
        }

        // Tạo user mới
        $hashedPassword = Hash::make($password);
        $now = Carbon::now()->toDateTimeString();

        DB::insert(
            "INSERT INTO users (full_name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashedPassword, 'customer', $now, $now]
        );

        // Tự động đăng nhập và trả token sau khi đăng ký
        $credentials = ['email' => $email, 'password' => $password];
        $token = auth('api')->attempt($credentials);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!',
            'access_token' => $token,
            'refresh_token' => $token, // JWT dùng cùng 1 token để refresh
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60, // tính bằng giây
        ], 201);
    }

    /**
     * Đăng nhập và nhận JWT token.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        $token = auth('api')->attempt($credentials);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email hoặc mật khẩu không chính xác!'
            ], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ], 200);
    }

    /**
     * Refresh token — dùng token cũ để nhận token mới.
     */
    public function refresh()
    {
        $newToken = auth('api')->refresh();

        return response()->json([
            'status' => 'success',
            'access_token' => $newToken,
            'refresh_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Lấy thông tin user đang đăng nhập.
     */
    public function me()
    {
        $user = auth('api')->user();

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    /**
     * Đăng xuất — hủy token hiện tại (blacklist).
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng xuất thành công!'
        ]);
    }
}