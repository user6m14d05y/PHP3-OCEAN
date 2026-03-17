<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
<<<<<<< HEAD

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;

// Add this line to run the route: http://localhost:8000/api
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Welcome to the API!'
    ]);
});

// Form sent to php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/Login', [AuthController::class, 'Login']);
Route::post('/SubmitContact', [ContactController::class, 'SubmitContact']);

// use middleware auth:sanctum
Route::middleware('auth:sanctum')->post('/Logout', [AuthController::class, 'Logout']);

// Test get data from database mysql
Route::get('/users', function () {
    $users = \Illuminate\Support\Facades\DB::table('users')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});

Route::get('/contact', function () {
    $users = \Illuminate\Support\Facades\DB::table('contact')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});
=======
use App\Http\Controllers\ProductController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::apiResource('products', ProductController::class);
>>>>>>> 85eed9c2 (first commit)
