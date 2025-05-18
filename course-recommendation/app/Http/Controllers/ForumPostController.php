<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForumPost\StoreForumPostRequest;
use App\Http\Requests\ForumPost\UpdateForumPostRequest;
use App\Models\ForumPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Course_Instructors;
use App\Models\Enrollment;


class ForumPostController extends Controller
{
    public function index(): JsonResponse
    {
        $forumPosts = ForumPost::all();
        return response()->json(['data' => $forumPosts]);
    }

    public function show($id): JsonResponse
    {
        $forumPost = ForumPost::findOrFail($id);
        return response()->json(['data' => $forumPost]);
    }

    public function store(StoreForumPostRequest $request): JsonResponse
    {
        $forumPost = ForumPost::create($request->validated());
        return response()->json(['message' => 'Forum post created successfully', 'data' => $forumPost], 201);
    }

    public function update(UpdateForumPostRequest $request, $id): JsonResponse
    {
        $forumPost = ForumPost::findOrFail($id);
        $forumPost->update($request->validated());
        return response()->json(['message' => 'Forum post updated successfully', 'data' => $forumPost]);
    }

    public function destroy($id): JsonResponse
    {
        $forumPost = ForumPost::findOrFail($id);
        $forumPost->delete();
        return response()->json(['message' => 'Forum post deleted successfully']);
    }
     public function indexForStudent($course_id)
    {
        $user = Auth::user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        $posts = ForumPost::where('course_id', $course_id)->with('user')->get();
        return response()->json($posts, 200);
    }

    public function storeForStudent(Request $request, $course_id)
    {
        $user = Auth::user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = ForumPost::create([
            'user_id' => $user->id,
            'course_id' => $course_id,
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json($post, 201);
    }

    public function indexForInstructor($course_id)
    {
        $user = Auth::user();
        $instructor = Course_Instructors::where('course_id', $course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $posts = ForumPost::where('course_id', $course_id)->with('user')->get();
        return response()->json($posts, 200);
    }

    public function storeForInstructor(Request $request, $course_id)
    {
        $user = Auth::user();
        $instructor = Course_Instructors::where('course_id', $course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = ForumPost::create([
            'user_id' => $user->id,
            'course_id' => $course_id,
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json($post, 201);
    }

    public function flag(Request $request, $id)
    {
        $post = ForumPost::find($id);

        if (!$post) {
            return response()->json(['message' => 'Forum post not found'], 404);
        }

        // Add a flag field to the forum_posts table if needed
        $post->update(['flagged' => 1]); // Assuming a flagged column exists
        return response()->json(['message' => 'Forum post flagged successfully'], 200);
    }

    public function remove($id)
    {
        $post = ForumPost::find($id);

        if (!$post) {
            return response()->json(['message' => 'Forum post not found'], 404);
        }

        $post->delete();
        return response()->json(['message' => 'Forum post removed successfully'], 200);
    }
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = ForumPost::onlyTrashed()->get();
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = ForumPost::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Forum post restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = ForumPost::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Forum post permanently deleted'], 200);
    }

    // Forum Posts
function search(Request $request) {
    return ForumPost::query()
        ->when($request->filled('title'), fn($q) => $q->where('title', 'like', "%{$request->input('title')}%"))
        ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')))
        ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->input('course_id')))
        ->get();
    }
}