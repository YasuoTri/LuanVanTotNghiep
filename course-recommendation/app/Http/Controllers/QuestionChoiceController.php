<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionChoiceRequest;
use App\Http\Requests\UpdateQuestionChoiceRequest;
use App\Models\QuestionChoice;
use Illuminate\Http\Request;

class QuestionChoiceController extends Controller
{
    public function index()
    {
        $choices = QuestionChoice::with('question')->paginate(10);
        return response()->json($choices);
    }

    public function store(StoreQuestionChoiceRequest $request)
    {
        $choice = QuestionChoice::create($request->validated());
        return response()->json($choice, 201);
    }

    public function show(Request $request,$id)
    {
        $choice = QuestionChoice::with("question")->find($id);
        return response()->json($choice);
    }
  
   public function update(UpdateQuestionChoiceRequest $request,$id)
    {
        $choice= QuestionChoice::find($id);
        if (!$choice) {
            return response()->json(['message' => 'Choice not found'], 404);
        }
        $choice->fill($request->validated());
        if (!$choice->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
        $choice->update($request->validated());
        return response()->json(['message'=>"updated successfully",'choice'=>$choice], 200);
    }

    public function destroy(Request $request ,$id)
    {
  $choice= QuestionChoice::find($id);
        if (!$choice) {
            return response()->json(['message' => 'Choice not found'], 404);
        }
        $choice->delete();
        return response()->json(['message'=>"deleted successfully",'choice'=>$choice], 200);
    }

 function search(Request $request) {
    return QuestionChoice::query()
        ->when($request->filled('question_id'), fn($q) => $q->where('question_id', $request->input('question_id')))
        ->when($request->filled('is_correct'), fn($q) => $q->where('is_correct', $request->input('is_correct')))
        ->paginate(10);
}
}