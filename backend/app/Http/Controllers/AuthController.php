<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        $checkEmail = \Illuminate\Support\Facades\DB::select("SELECT * FROM users WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422); 
        }

        $hashedPassword = \Illuminate\Support\Facades\Hash::make($password);
        $now = \Carbon\Carbon::now()->toDateTimeString(); 

        \Illuminate\Support\Facades\DB::insert(
            "INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashedPassword, 'user', $now, $now]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!'
        ], 201); 
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $sql = "SELECT * FROM users WHERE email = ?";
        $result = \Illuminate\Support\Facades\DB::select($sql, [$email]);

        if (count($result) > 0) {
            $user = $result[0];
            if (\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                // Generate token
                $userModel = User::find($user->id);
                $token = $userModel->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Đăng nhập thành công!',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role 
                    ]
                ], 200);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Email hoặc mật khẩu không chính xác!'
            ], 422);
        }
    }

    public function Logout(Request $request)
    {
        // delete token
        $request->user()->currentAccessToken()->delete(); 
        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng xuất thành công!'
        ]);
    }
}