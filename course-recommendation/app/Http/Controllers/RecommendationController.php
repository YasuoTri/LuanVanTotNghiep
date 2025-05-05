<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Course;
use App\Models\Interaction;
use App\Models\User;

class RecommendationController extends Controller
{
    public function getCourses()
    {
        $courses = Course::pluck('course_name')->toArray();
        return response()->json(['courses' => $courses]);
    }

    public function recommend(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'course_name' => 'nullable|string|exists:courses,course_name',
        ]);

        $userId = $request->input('user_id');
        $courseName = $request->input('course_name');

        // Gọi API FastAPI
        $response = Http::post('http://localhost:8000/recommend', [
            'user_id' => $userId,
            'course_name' => $courseName,
        ]);

        if ($response->successful()) {
            return response()->json([
                'recommended_courses' => $response->json()['courses'],
                'selected_course' => $courseName,
                'user_id' => $userId,
            ]);
        }

        return response()->json(['error' => $response->json()['detail'] ?? 'Error fetching recommendations'], 400);
    }

    public function rate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'course_name' => 'required|string|exists:courses,course_name',
            'rating' => 'required|numeric|min:1|max:5',
        ]);

        $course = Course::where('course_name', $request->course_name)->first();

        // Gọi API FastAPI để lưu đánh giá
        $response = Http::post('http://localhost:8000/rate', [
            'user_id' => (int)$request->user_id,
            'course_id' => $course->id,
            'rating' => (float)$request->rating,
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Rating submitted successfully']);
        }

        return response()->json(['error' => 'Error submitting rating'], 400);
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'userid_DI' => 'required|string|unique:users,userid_DI',
            'email' => 'nullable|email|unique:users,email',
            'final_cc_cname_DI' => 'nullable|string',
            'LoE_DI' => 'nullable|string',
            'YoB' => 'nullable|integer',
            'gender' => 'nullable|string',
        ]);

        $user = User::create($request->all());
        return response()->json(['user' => $user], 201);
    }
}