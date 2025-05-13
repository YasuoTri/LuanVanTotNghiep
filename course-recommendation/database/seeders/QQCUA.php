<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\QuizResult;
use App\Models\UserAnswer;
class QQCUA extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedQuestions();
        $this->seedQuestionChoices();
        $this->seedUserAnswers();
    }
    /**
 * Seed questions table
 */
private function seedQuestions()
{
    $this->command->info('Seeding questions...');

    $quizzes = Quiz::all();
    $questionTypes = ['multiple_choice', 'true_false', 'open_ended'];

    foreach ($quizzes as $quiz) {
        $questionCount = rand(1, 5);

        for ($i = 1; $i <= $questionCount; $i++) {
            $questionType = $questionTypes[array_rand($questionTypes)];
            $points = rand(100, 500) / 100; // 1.00 to 5.00

            Question::create([
                'quiz_id' => $quiz->id,
                'title' => "Question $i for Quiz {$quiz->id}: Sample Question",
                'question_type' => $questionType,
                'points' => $points,
                'sort_order' => $i,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $this->command->info('Questions seeded successfully!');
}

/**
 * Seed question_choices table
 */
private function seedQuestionChoices()
{
    $this->command->info('Seeding question choices...');

    $questions = Question::all();

    foreach ($questions as $question) {
        if ($question->question_type === 'multiple_choice') {
            // Create 4 choices for multiple_choice
            for ($i = 1; $i <= 4; $i++) {
                $isCorrect = ($i === 1) ? 1 : 0; // First choice is correct
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'content' => "Choice $i for Question {$question->id}",
                    'is_correct' => $isCorrect,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($question->question_type === 'true_false') {
            // Create 2 choices for true_false
            QuestionChoice::create([
                'question_id' => $question->id,
                'content' => 'True',
                'is_correct' => rand(0, 1), // Randomly true or false is correct
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            QuestionChoice::create([
                'question_id' => $question->id,
                'content' => 'False',
                'is_correct' => rand(0, 1) ? 0 : 1, // Opposite of the first
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // No choices for open_ended questions
    }

    $this->command->info('Question choices seeded successfully!');
}

/**
 * Seed user_answers table
 */
private function seedUserAnswers()
{
    $this->command->info('Seeding user answers...');

    $quizResults = QuizResult::all();

    foreach ($quizResults as $quizResult) {
        $questions = Question::where('quiz_id', $quizResult->quiz_id)->get();

        foreach ($questions as $question) {
            $answerData = [
                'user_id' => $quizResult->user_id,
                'quiz_result_id' => $quizResult->id,
                'question_id' => $question->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($question->question_type === 'open_ended') {
                // Provide a sample text answer for open_ended
                $answerData['answer_text'] = "Sample answer for question {$question->id}";
                $answerData['is_correct'] = rand(0, 1); // Randomly correct or incorrect
                $answerData['points_earned'] = $answerData['is_correct'] ? $question->points : 0;
            } else {
                // For multiple_choice and true_false, select a random choice
                $choices = QuestionChoice::where('question_id', $question->id)->get();
                if ($choices->isNotEmpty()) {
                    $selectedChoice = $choices->random();
                    $answerData['choice_id'] = $selectedChoice->id;
                    $answerData['is_correct'] = $selectedChoice->is_correct;
                    $answerData['points_earned'] = $selectedChoice->is_correct ? $question->points : 0;
                }
            }

            UserAnswer::create($answerData);
        }
    }

    $this->command->info('User answers seeded successfully!');
}
}
