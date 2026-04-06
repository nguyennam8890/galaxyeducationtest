<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
});

// Public routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Category management
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Course management
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);

    // Enrollment
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
    Route::post('/courses/{course}/cancel', [EnrollmentController::class, 'cancel']);
    Route::put('/courses/{course}/progress', [EnrollmentController::class, 'updateProgress']);

    // Export
    Route::get('/export/users', [\App\Http\Controllers\Api\ExportController::class, 'exportUsers']);
    Route::get('/export/enrollments', [\App\Http\Controllers\Api\ExportController::class, 'exportEnrollments']);
});

// Rate limit demo routes
Route::get('/limited', function () {
    return 'Bạn được phép truy cập.';
})->middleware('check.ip');

Route::middleware('throttle:5,1')->get('/limited2', function () {
    return 'Bạn được phép truy cập!';
});
