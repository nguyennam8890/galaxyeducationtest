<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
//Custom throttle
Route::get('/limited', function () {
    return 'Bạn được phép truy cập.'; }
)->middleware('check.ip');

// Dùng throttle có sẵn  của laravel
// 5: số request tối đa
// 1: thời gian tính bằng phút
Route::middleware('throttle:5,1')->get('/limited2', function () {
    return 'Bạn được phép truy cập!';
});
