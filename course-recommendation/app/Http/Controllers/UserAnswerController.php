<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserAnswerRequest;
use App\Http\Requests\UpdateUserAnswerRequest;
use App\Models\UserAnswer;
use App\Models\QuestionChoice;
use Illuminate\Http\Request;

class UserAnswerController extends Controller
{
    public function index()
    {
        $answers = UserAnswer::with(['user', 'question', 'choice'])->get();
        return response()->json($answers);
    }

    public function store(StoreUserAnswerRequest $request)
    {
        $data = $request->validated();

        // Kiểm tra đáp án đúng (nếu là trắc nghiệm)
        if ($data['choice_id']) {
            $choice = QuestionChoice::find($data['choice_id']);
            $data['is_correct'] = $choice->is_correct;
            $data['points_earned'] = $choice->is_correct ? $choice->question->points : 0;
        }

        $answer = UserAnswer::create($data);
        return response()->json($answer, 201);
    }

    public function show(UserAnswer $answer)
    {
        return response()->json($answer->load(['user', 'question', 'choice']));
    }

    public function update(UpdateUserAnswerRequest $request, UserAnswer $answer)
    {
        $data = $request->validated();

        if ($data['choice_id']) {
            $choice = QuestionChoice::find($data['choice_id']);
            $data['is_correct'] = $choice->is_correct;
            $data['points_earned'] = $choice->is_correct ? $choice->question->points : 0;
        }

        $answer->update($data);
        return response()->json($answer);
    }

    public function destroy(UserAnswer $answer)
    {
        $answer->delete();
        return response()->json(null, 204);
    }
}