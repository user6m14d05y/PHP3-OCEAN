<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminUserController;

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
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus']);
});

// Business routes
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class);
Route::get('productsAll', [ProductController::class, 'all']);
Route::get('products/{id}/edit', [ProductController::class, 'edit']);

// Test & Debug routes
Route::get('/contact', function () {
    $users = \Illuminate\Support\Facades\DB::table('contact')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});
