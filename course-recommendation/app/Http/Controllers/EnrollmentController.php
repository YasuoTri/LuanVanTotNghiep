<?php

namespace App\Http\Controllers;

use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function index(): JsonResponse
    {
        $enrollments = Enrollment::all();
        return response()->json(['data' => $enrollments]);
    }

    public function show($id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        return response()->json(['data' => $enrollment]);
    }

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $enrollment = Enrollment::create($request->validated());
        return response()->json(['message' => 'Enrollment created successfully', 'data' => $enrollment], 201);
    }

    public function update(UpdateEnrollmentRequest $request, $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->update($request->validated());
        return response()->json(['message' => 'Enrollment updated successfully', 'data' => $enrollment]);
    }

    public function destroy($id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->delete();
        return response()->json(['message' => 'Enrollment deleted successfully']);
    }
}