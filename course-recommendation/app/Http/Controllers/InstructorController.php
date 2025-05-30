<?php

namespace App\Http\Controllers;

use App\Http\Requests\Interaction\UpdateInteractionRequest;
use App\Http\Requests\StoreInstructorRequest;
use Illuminate\Http\Request;
use App\Models\Instructors;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

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
            return response()->json(['message' => 'Cannot delete instructor with associated user'], 400);
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

}
