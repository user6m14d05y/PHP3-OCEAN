<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminStaffController;

// Add this line to run the route: http://localhost:8000/api
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Welcome to the API!'
    ]);
});

// Auth routes (Public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/SubmitContact', [ContactController::class, 'SubmitContact']);

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
});

// Business routes
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::get('productsAll', [ProductController::class, 'all']);

// Test & Debug routes
Route::get('/contact', function () {
    $users = \Illuminate\Support\Facades\DB::table('contact')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});
