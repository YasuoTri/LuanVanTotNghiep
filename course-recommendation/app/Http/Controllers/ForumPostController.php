<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForumPost\StoreForumPostRequest;
use App\Http\Requests\ForumPost\UpdateForumPostRequest;
use App\Models\ForumPost;
use Illuminate\Http\JsonResponse;

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
}