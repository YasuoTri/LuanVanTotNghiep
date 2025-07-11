<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Student;
use App\Models\StudentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

public function createStudentProfile(Request $request): JsonResponse
{
    $user = Auth::user();

    // Check if student profile already exists
    if ($user->student) {
        return response()->json([
            'message' => 'Student profile already exists.'
        ], 409);
    }

    // Validate request data
    $validator = Validator::make($request->all(), [
        'LoE_DI' => 'string|nullable|max:50',
        'learning_goals' => 'string|nullable',
        'category_ids' => 'array|nullable',
        'category_ids.*' => 'integer|exists:categories,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    // Create student profile
    $student = Student::create([
        'user_id' => $user->id,
        'LoE_DI' => $request->input('LoE_DI', 'Unknown'),
        'learning_goals' => $request->input('learning_goals'),
    ]);

    // Associate categories if provided
    if ($request->has('category_ids') && !empty($request->input('category_ids'))) {
        $categoryIds = $request->input('category_ids');
        foreach ($categoryIds as $categoryId) {
            StudentCategory::create([
                'student_id' => $student->id,
                'category_id' => $categoryId,
            ]);
        }
    }

    return response()->json([
        'message' => 'Student profile created successfully',
        'data' => $student->load('categories') // Eager load categories for response
    ], 201);
}
}
