<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Xác thực Cloudflare Turnstile token
     */
    private function verifyTurnstile(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $secretKey = env('TURNSTILE_SECRET_KEY');

        if (!$secretKey) {
            // Nếu chưa cấu hình Turnstile, bỏ qua verification (dev mode)
            return true;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return $response->json('success', false);
        } catch (\Exception $e) {
            \Log::error('Turnstile verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate password: chữ hoa + số + ký tự đặc biệt + tối thiểu 8 ký tự
     */
    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự!';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ hoa!';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ số!';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt!';
        }
        return null; // Valid
    }

    public function register(Request $request)
    {
        // Verify Cloudflare Turnstile
        $turnstileToken = $request->input('turnstile_token');
        if (!$this->verifyTurnstile($turnstileToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'
            ], 422);
        }

        $name = $request->input('full_name') ?? $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        // Password validation
        $passwordError = $this->validatePassword($password);
        if ($passwordError) {
            return response()->json([
                'status' => 'error',
                'message' => $passwordError
            ], 422);
        }

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
        // Verify Cloudflare Turnstile
        $turnstileToken = $request->input('turnstile_token');
        if (!$this->verifyTurnstile($turnstileToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'
            ], 422);
        }

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

    protected function respondWithToken($token, $guardType)
    {
        $user = ($guardType === 'admin') ? auth('admin')->user() : auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'role' => $user->role,
            'user' => [
                'id' => $guardType === 'admin' ? $user->admin_id : $user->user_id,
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