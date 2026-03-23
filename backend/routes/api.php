<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CouponController;

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
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('products/edit/{id}', [ProductController::class, 'edit']);
});

// Customer Profile routes (Protected - cần JWT token user/admin)
Route::middleware('auth:api,admin')->prefix('profile')->group(function () {
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);
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

    // Quản lý Mã giảm giá
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::put('/coupons/{id}', [CouponController::class, 'update']);
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
    
});
Route::get('products/{id}', [ProductController::class, 'show']);
// Business routes
Route::get('products', [ProductController::class, 'index']);
Route::apiResource('categories', CategoryController::class);
Route::get('productsAll', [ProductController::class, 'all']);

Route::get('brands', [BrandController::class, 'index']);

// API Địa chỉ Việt Nam (Public)
Route::prefix('location')->group(function () {
    Route::get('/provinces', [LocationController::class, 'getProvinces']);
    Route::get('/districts/{provinceCode}', [LocationController::class, 'getDistricts']);
    Route::get('/wards/{districtCode}', [LocationController::class, 'getWards']);
    Route::get('/search', [LocationController::class, 'search']);
});
