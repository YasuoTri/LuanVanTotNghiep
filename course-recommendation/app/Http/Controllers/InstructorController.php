<?php

namespace App\Http\Controllers;

use App\Http\Requests\Interaction\UpdateInteractionRequest;
use App\Http\Requests\StoreInstructorRequest;
use Illuminate\Http\Request;
use App\Models\Instructors;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InstructorController extends Controller
{
    public function index()
    {
        $instructors = Instructors::paginate(10);
        return response()->json($instructors);
    }
      public function indexWithoutAuthentication()
    {
        $users=User::with('instructor')->where('role', 'instructor')->paginate(10);
        if ($users->isEmpty()) {
            return response()->json(['message' => 'No instructors found'], 404);
        }
        return response()->json($users);
    }
    
    public function show($id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        return response()->json($instructor);
    }
    public function store(StoreInstructorRequest $request)
    {
        $instructor = Instructors::create($request->all());
        return response()->json($instructor, 201);
    }
    public function update(UpdateInteractionRequest $request, $id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        $instructor->update($request->all());
        return response()->json($instructor);
    }
    public function destroy($id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        // Check if the instructor has any associated courses
        if ($instructor->courses()->exists()) {
            return response()->json(['message' => 'Cannot delete instructor with associated courses'], 400);
        }
       $user = User::find($instructor->user_id);
        if ($user) {
            $user->delete();
        }
        $instructor->delete();
        return response()->json(['message' => 'Instructor deleted successfully']);
    }

     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Instructors::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Instructors::onlyTrashed()->findOrFail($id);
        if (!$enrollment) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        $user = User::withTrashed()->find($enrollment->user_id);
        if ($user) {
            $user->restore();
        }
        $enrollment->restore();
        return response()->json(['message' => 'Instructor restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = Instructors::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Instructor permanently deleted'], 200);
    }

    public function search(Request $request)
{
    $query = Instructors::query();

    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    if ($request->filled('organization')) {
        $query->where('organization', 'like', '%' . $request->organization . '%');
    }

    return response()->json($query->paginate(10));
}
/**
     * Get the top 10 outstanding instructors based on enrollments, course ratings, and course count.
     *
     * @return JsonResponse
     */
    public function getTopInstructors(): JsonResponse
    {
        try {
            // Query to get top 10 instructors
            $topInstructors = Instructors::select([
                'instructors.id as instructor_id',
                'users.username as instructor_name',
                DB::raw('COUNT(DISTINCT course_instructors.course_id) as course_count'),
                DB::raw('COALESCE(AVG(courses.course_rating), 0) as avg_course_rating'),
                DB::raw('COUNT(DISTINCT enrollments.id) as total_enrollments')
            ])
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->leftJoin('course_instructors', 'instructors.id', '=', 'course_instructors.instructor_id')
            ->leftJoin('courses', function ($join) {
                $join->on('course_instructors.course_id', '=', 'courses.id')
                     ->whereNull('courses.deleted_at'); // Exclude soft-deleted courses
            })
            ->leftJoin('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->groupBy('instructors.id', 'users.username')
            ->orderByDesc('total_enrollments') // Primary sort: total enrollments
            ->orderByDesc('avg_course_rating') // Secondary sort: average rating
            ->orderByDesc('course_count') // Tertiary sort: course count
            ->take(10) // Limit to top 10
            ->get();

            // Format the response
            $response = $topInstructors->map(function ($instructor) {
                // Ensure instructor profile is not null
                $instructor_info=Instructors::find($instructor->instructor_id);
                return [
                    'instructor_id' => $instructor->instructor_id,
                    'name' => $instructor->instructor_name,
                    'instructor_profile' => $instructor_info ??null,
                    'course_count' => $instructor->course_count,
                    'avg_course_rating' => round($instructor->avg_course_rating, 2),
                    'total_enrollments' => $instructor->total_enrollments,
                ];
            });

            return response()->json([
                'message' => 'Top 10 instructors retrieved successfully',
                'data' => $response
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving top instructors: ' . $e->getMessage());
            return response()->json([
                'error' => 'An error occurred while retrieving top instructors',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
