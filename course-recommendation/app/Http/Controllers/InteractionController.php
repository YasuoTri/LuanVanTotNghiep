<?php

namespace App\Http\Controllers;

use App\Http\Requests\Interaction\StoreInteractionRequest;
use App\Http\Requests\Interaction\UpdateInteractionRequest;
use App\Models\Interaction;
use Illuminate\Http\JsonResponse;

class InteractionController extends Controller
{
    public function index(): JsonResponse
    {
        $interactions = Interaction::paginate(10);
        return response()->json(['data' => $interactions]);
    }

    public function show($id): JsonResponse
    {
        $interaction = Interaction::findOrFail($id);
        return response()->json(['data' => $interaction]);
    }

    public function store(StoreInteractionRequest $request): JsonResponse
    {
        $interaction = Interaction::create($request->validated());
        return response()->json(['message' => 'Interaction created successfully', 'data' => $interaction], 201);
    }

    public function update(UpdateInteractionRequest $request, $id): JsonResponse
    {
        $interaction = Interaction::findOrFail($id);
        $interaction->fill($request->validated());
        if (!$interaction->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
        $interaction->update($request->validated());
        return response()->json(['message' => 'Interaction updated successfully', 'data' => $interaction]);
    }

    public function destroy($id): JsonResponse
    {
        $interaction = Interaction::findOrFail($id);
        $interaction->delete();
        return response()->json(['message' => 'Interaction deleted successfully']);
    }
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Interaction::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Interaction::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Interaction restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = Interaction::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Interaction permanently deleted'], 200);
    }
}