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
        $interactions = Interaction::all();
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
        $interaction->update($request->validated());
        return response()->json(['message' => 'Interaction updated successfully', 'data' => $interaction]);
    }

    public function destroy($id): JsonResponse
    {
        $interaction = Interaction::findOrFail($id);
        $interaction->delete();
        return response()->json(['message' => 'Interaction deleted successfully']);
    }
}