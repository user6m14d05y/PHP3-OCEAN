<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Thêm đoạn này để chạy route: http://localhost:8383/api
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Chào mừng bạn đến với API!'
    ]);
});

// Thêm route này để test lấy dữ liệu từ DB (giống với dữ liệu bạn thấy trên HeidiSQL)
Route::get('/users', function () {
    $users = \Illuminate\Support\Facades\DB::table('users')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});
Route::get('/users', function () {
    $users = \Illuminate\Support\Facades\DB::table('users')->get();
    return response()->json([
        'status' => 'success',
        'data' => $users
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
