<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(){
        // Fetch all students
        $students = Student::paginate(10);
        return response()->json($students);
    }
    public function show($id){
        // Fetch a single student by ID
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }
        return response()->json($student);
    }
    public function store(StoreStudentRequest $request){
        // Create a new student
        $student = Student::create($request->all());
        return response()->json($student, 201);
    }
    public function update(UpdateStudentRequest $request, $id){
        // Update an existing student
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }
        $student->fill($request->all());
        if (!$student->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
        $student->update($request->all());
        return response()->json($student);
    }
    public function destroy($id){
        // Delete a student
        $student = Student::find($id);
        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }
        $student->delete();
        return response()->json(['message' => 'Student deleted successfully']);
    }  
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments =Student::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment =Student::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Student restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment =Student::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Student permanently deleted'], 200);
    }

    public function search(Request $request)
{
    $query = Student::query();

    if ($request->filled('interests')) {
        $query->where('interests', 'like', '%' . $request->interests . '%');
    }

    if ($request->filled('learning_goals')) {
        $query->where('learning_goals', 'like', '%' . $request->learning_goals . '%');
    }

    return response()->json($query->paginate(10));
}

}
