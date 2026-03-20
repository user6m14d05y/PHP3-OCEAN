<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\ForgotPasswordController;

// Add this line to run the route: http://localhost:8000/api
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Welcome to the API!'
    ]);
});

// Auth routes (Public) — có Rate Limiting + Turnstile
Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:3,1')->post('/register', [AuthController::class, 'register']);
Route::post('/SubmitContact', [ContactController::class, 'SubmitContact']);

// Forgot Password routes (Public) — có Rate Limiting cho send OTP
Route::middleware('throttle:3,1')->post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword']);

// Google OAuth callback (Public)
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Auth routes (Protected - cần JWT token)
Route::middleware('auth:api,admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return auth('admin')->user() ?? auth('api')->user();
    });
});

Route::middleware(['auth:admin', 'role:admin,staff'])->prefix('admin')->group(function () {
    // Quản lý Khách hàng (bảng users)
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus']);

    // Quản lý Nhân sự (bảng admins)
    Route::get('/staff', [AdminStaffController::class, 'index']);
    Route::post('/staff', [AdminStaffController::class, 'store']);
    Route::put('/staff/{id}', [AdminStaffController::class, 'update']);
    Route::put('/staff/{id}/role', [AdminStaffController::class, 'updateRole']);
    Route::delete('/staff/{id}', [AdminStaffController::class, 'destroy']);

    // Quản lý Liên hệ
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
});

// Business routes
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::get('productsAll', [ProductController::class, 'all']);

