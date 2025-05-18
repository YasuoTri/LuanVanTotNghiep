<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        // Fetch all categories
        $categories = Category::all();
        return response()->json($categories);
    }
    public function show($id)
    {
        // Fetch a single category by ID
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        return response()->json($category);
    }
    public function store(Request $request)
    {
        // Create a new category
        $category = Category::create($request->all());
        return response()->json($category, 201);
    }
    public function update(Request $request, $id)
    {
        // Update an existing category
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        $category->update($request->all());
        return response()->json($category);
    }
    public function destroy($id)
    {
        // Delete a category
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
    
    //
}
