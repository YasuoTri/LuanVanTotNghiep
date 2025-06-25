<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommendationController extends Controller
{
    public function getRecommendations(Request $request)
    {
        $userId = $request->input('user_id');

        // Kiểm tra xem user đã enroll khóa học nào chưa
        $enrolledCourse = $this->checkEnrolledCourse($userId);

        $pythonApiUrl = 'http://python-recommendation-service:5000/recommend'; // URL của Python service

        if ($enrolledCourse) {
            // Nếu đã enroll, dùng course đã enroll để chạy CBF
            $response = Http::post($pythonApiUrl, [
                'user_id' => $userId,
                'course_title' => $enrolledCourse->course_title,
                'type' => 'cbf_with_course'
            ]);
        } else {
            // Nếu chưa enroll, lấy learning_goal và student_category từ profile
            $userProfile = $this->getUserProfile($userId);
            $response = Http::post($pythonApiUrl, [
                'user_id' => $userId,
                'learning_goal' => $userProfile->learning_goal ?? null,
                'user_categories' => $userProfile->student_category ?? null,
                'type' => 'cold_start'
            ]);
        }

        if ($response->successful()) {
            return response()->json($response->json(), 200);
        } else {
            return response()->json(['error' => 'Failed to get recommendations'], 500);
        }
    }

    private function checkEnrolledCourse($userId)
    {
        // Giả lập truy vấn database để kiểm tra enrollment
        // Thay bằng logic thực tế với Eloquent hoặc query builder
        $enrollment = \App\Models\Enrollment::where('user_id', $userId)->first();
        return $enrollment ? $enrollment->course : null;
    }

    private function getUserProfile($userId)
    {   
        $user= User::find($userId);
        if (!$user) {
            return null; // Trả về null nếu không tìm thấy người dùng
        }
        $learnerProfile=Student::where('user_id', $userId)->first();
        if (!$learnerProfile) {
            return null; // Trả về null nếu không tìm thấy profile
        }
        // Giả lập lấy thông tin profile từ database
        // Thay bằng logic thực tế với Eloquent
        return $learnerProfile; 
    }
}