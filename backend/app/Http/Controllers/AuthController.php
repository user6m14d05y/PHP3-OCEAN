<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        // Check email validate
        $checkEmail = \Illuminate\Support\Facades\DB::select("SELECT * FROM users WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422); 
        }

        // Hash password
        $hashedPassword = \Illuminate\Support\Facades\Hash::make($password);
        $now = \Carbon\Carbon::now()->toDateTimeString(); 

        // Insert data to sql
        \Illuminate\Support\Facades\DB::insert(
            "INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashedPassword, 'user', $now, $now]
        );

        // Report success to interface
        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!'
        ], 201); 
    }

    // Login to path/ admin or user
    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // Get user information by email
        $sql = "SELECT * FROM users WHERE email = ?";
        $result = \Illuminate\Support\Facades\DB::select($sql, [$email]);

        // The result array will have 1 element (the user information) if the email exists
        if (count($result) > 0) {
            $user = $result[0];

            // Compare the entered password with the hash (HASH) in the database
            if (\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Đăng nhập thành công!',
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
}