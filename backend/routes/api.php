<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

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

// Test get data from database mysql
Route::get('/users', function () {
    $users = \Illuminate\Support\Facades\DB::table('users')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});
