<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->input('full_name') ?? $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        $checkEmail = DB::select("SELECT * FROM users WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422);
        }

        $hashedPassword = Hash::make($password);
        $now = Carbon::now()->toDateTimeString();

        DB::insert(
            "INSERT INTO users (full_name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashedPassword, 'customer', $now, $now]
        );

        $credentials = ['email' => $email, 'password' => $password];
        $token = auth('api')->attempt($credentials);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // BƯỚC 1: Thử đăng nhập Admin (nhân sự) trước
        if ($token = auth('admin')->attempt($credentials)) {
            return $this->respondWithToken($token, 'admin');
        }

        // BƯỚC 2: Thử đăng nhập Customer
        if ($token = auth('api')->attempt($credentials)) {
            return $this->respondWithToken($token, 'customer');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Email hoặc mật khẩu không chính xác!'
        ], 401);
    }

    protected function respondWithToken($token, $role)
    {
        $user = ($role === 'admin') ? auth('admin')->user() : auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'role' => $role,
            'user' => [
                'id' => $role === 'admin' ? $user->admin_id : $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ], 200);
    }

    public function refresh()
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        $newToken = auth($guard)->refresh();

        return response()->json([
            'status' => 'success',
            'access_token' => $newToken,
            'refresh_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => auth($guard)->factory()->getTTL() * 60,
        ]);
    }

    public function me()
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        $user = auth($guard)->user();

        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $guard === 'admin' ? $user->admin_id : $user->user_id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    public function logout()
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        auth($guard)->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng xuất thành công!'
        ]);
    }
}