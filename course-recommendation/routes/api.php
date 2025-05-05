<?php
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
Route::get('/courses', [RecommendationController::class, 'getCourses']);
Route::post('/recommend', [RecommendationController::class, 'recommend']);
Route::post('/rate', [RecommendationController::class, 'rate']);
Route::post('/users', [RecommendationController::class, 'createUser']);

// API routes không yêu cầu CSRF token
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');