<?php
namespace App\Services;

use App\Models\{Lesson, LessonProgress, Quiz, QuizResult, CertificateRule};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificateEligibilityChecker
{
    protected int $courseId;
    protected int $userId;
    protected CertificateRule|null $rule;

    public function __construct(int $courseId, int $userId)
    {
        $this->courseId = $courseId;
        $this->userId = $userId;
        $this->rule = CertificateRule::where('course_id', $courseId)->first();
    }

    public function check(): array
    {
        // Use database defaults if rule is null
        $lessonPercentRequired = $this->rule?->lesson_completion_percent ?? 100;
        $quizMinScore = $this->rule?->quiz_min_score ?? 60;
        $lessonVersionRule = $this->rule?->lesson_version_rule ?? 'latest';
        $quizVersionRule = $this->rule?->quiz_version_rule ?? 'latest';

        // 1. Get lesson groups and their completion status
        $lessonQuery = Lesson::where('course_id', $this->courseId)
            ->where('is_visible', true)
            ->selectRaw('DISTINCT COALESCE(origin_id, id) as origin_id');
        
        $lessonOriginIds = $lessonQuery->pluck('origin_id');
        $totalLessons = $lessonOriginIds->count();
        $completedCount = 0;
        $missingLessons = collect();

        if ($totalLessons > 0) {
            $lessonIdsQuery = Lesson::where('course_id', $this->courseId)
                ->where('is_visible', true);

            if ($lessonVersionRule === 'latest') {
                $lessonIdsQuery->whereIn('id', function ($query) {
                    $query->selectRaw('MAX(id)')
                        ->from('lessons')
                        ->where('is_visible', true)
                        ->where('course_id', $this->courseId)
                        ->groupBy(DB::raw('IFNULL(origin_id, id)'));
                });
            } else {
                $lessonIdsQuery->where(function ($q) use ($lessonOriginIds) {
                    $q->whereIn('origin_id', $lessonOriginIds)
                      ->orWhereIn('id', $lessonOriginIds);
                });
            }

            $lessonIds = $lessonIdsQuery->pluck('id');

            $completedLessons = LessonProgress::where('user_id', $this->userId)
                ->whereIn('lesson_id', $lessonIds)
                ->where('status', 'completed')
                ->pluck('lesson_id')
                ->groupBy(function ($lessonId) {
                    $lesson = Lesson::find($lessonId);
                    return $lesson->origin_id ?? $lesson->id;
                });

            $completedCount = $completedLessons->count();
            $missingLessons = $lessonOriginIds->diff($completedLessons->keys());

            Log::info('CertificateEligibilityChecker: Lesson check', [
                'course_id' => $this->courseId,
                'user_id' => $this->userId,
                'lesson_origin_ids' => $lessonOriginIds->toArray(),
                'completed_lesson_groups' => $completedLessons->keys()->toArray(),
                'missing_lessons' => $missingLessons->toArray(),
                'lesson_version_rule' => $lessonVersionRule,
            ]);
        }

        $lessonPercent = $totalLessons > 0
            ? round($completedCount / $totalLessons * 100, 2)
            : 100;

        // 2. Get quiz groups and their passing status
        $lessonIds = Lesson::where('course_id', $this->courseId)
            ->where('is_visible', true) // Ensure only visible lessons
            ->pluck('id');
        $quizQuery = Quiz::whereIn('lesson_id', $lessonIds)
            ->where('is_visible', true)
            ->selectRaw('DISTINCT COALESCE(origin_id, id) as origin_id');

        $quizOriginIds = $quizQuery->pluck('origin_id');
        $missingQuizzes = collect();
        $passedQuizIds = collect();

        if ($quizOriginIds->count() > 0) {
            $quizIdsQuery = Quiz::whereIn('lesson_id', $lessonIds)
                ->where('is_visible', true);

            if ($quizVersionRule === 'latest') {
                $quizIdsQuery->whereIn('id', function ($query) use ($lessonIds) {
                    $query->selectRaw('MAX(id)')
                        ->from('quizzes')
                        ->where('is_visible', true)
                        ->whereIn('lesson_id', $lessonIds)
                        ->groupBy(DB::raw('IFNULL(origin_id, id)'));
                });
            } else {
                $quizIdsQuery->where(function ($q) use ($quizOriginIds) {
                    $q->whereIn('origin_id', $quizOriginIds)
                      ->orWhereIn('id', $quizOriginIds);
                });
            }

            $quizIds = $quizIdsQuery->pluck('id');

            $passedQuizzes = QuizResult::where('user_id', $this->userId)
                ->whereIn('quiz_id', $quizIds)
                ->where('score', '>=', $quizMinScore)
                ->pluck('quiz_id')
                ->groupBy(function ($quizId) {
                    $quiz = Quiz::find($quizId);
                    return $quiz->origin_id ?? $quiz->id;
                });

            $passedQuizIds = $passedQuizzes->keys();
            $missingQuizzes = $quizOriginIds->diff($passedQuizIds);

            // Detailed logging for quizzes
            $quizDetails = Quiz::whereIn('id', $quizIds)->get()->map(function ($quiz) {
                return [
                    'quiz_id' => $quiz->id,
                    'origin_id' => $quiz->origin_id ?? $quiz->id,
                    'title' => $quiz->title,
                    'version' => $quiz->version,
                    'is_visible' => $quiz->is_visible,
                ];
            });

            Log::info('CertificateEligibilityChecker: Quiz check', [
                'course_id' => $this->courseId,
                'user_id' => $this->userId,
                'quiz_origin_ids' => $quizOriginIds->toArray(),
                'quiz_ids' => $quizIds->toArray(),
                'quiz_details' => $quizDetails->toArray(),
                'passed_quiz_ids' => $passedQuizIds->toArray(),
                'missing_quizzes' => $missingQuizzes->toArray(),
                'quiz_version_rule' => $quizVersionRule,
            ]);
        }

        // 3. Evaluate eligibility
        $eligibleLessons = $lessonPercent >= $lessonPercentRequired;
        $eligibleQuizzes = $quizOriginIds->isEmpty() || $missingQuizzes->isEmpty();

        return [
            'eligible' => $eligibleLessons && $eligibleQuizzes,
            'lessonPercent' => $lessonPercent,
            'missingLessons' => $missingLessons,
            'missingQuizzes' => $missingQuizzes,
            'rule' => [
                'lesson_required' => $lessonPercentRequired,
                'quiz_required' => $quizMinScore,
                'lesson_version_rule' => $lessonVersionRule,
                'quiz_version_rule' => $quizVersionRule,
            ],
        ];
    }
}