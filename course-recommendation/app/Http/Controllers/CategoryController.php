<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Traits\DetectAndUpdateIfChanged;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    use DetectAndUpdateIfChanged;
public function index()
{
    $categories = Category::withCount([
        'courses as courses_count' => function ($query) {
            $query->where('status', 'approved');
        }
    ])->get();

    return response()->json($categories);
}

// public function getCategoryWithSubcategories()
// {
//     $categories = Category::with([
//             'children' => function ($query) {
//                 $query->withCount('courses');
//             }
//         ])
//         ->withCount('courses', 'children')
//         ->whereNull('parent_id')
//         ->get();

//     return $categories;
// }

public function getCategoryWithSubcategories()
{
    $categories = Category::with([
        'children' => function ($query) {
            $query->withCount([
                'courses as courses_count' => function ($q) {
                    $q->where('status', 'approved');
                }
            ]);
        }
    ])
    ->withCount([
        'courses as courses_count' => function ($query) {
            $query->where('status', 'approved');
        },
        'children'
    ])
    ->whereNull('parent_id')
    ->get();

    return $categories;
}


// public function getSubcategories()
// {
//     $categories = Category::withCount('courses')
//         ->where('parent_id','!=',NULL) // Chỉ lấy category cha
//         ->get();

//     return $categories;
// }
public function getSubcategories()
{
    $categories = Category::withCount([
        'courses as courses_count' => function ($query) {
            $query->where('status', 'approved');
        }
    ])
    ->whereNotNull('parent_id') // chỉ lấy subcategory
    ->get();

    return $categories;
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
    $request->validate([
        'name' => 'required|unique:categories,name',
        'parent_id' => 'nullable|exists:categories,id',
    ]);

    // Kiểm tra nếu parent_id có cha ⇒ vi phạm 2 cấp
    if ($request->parent_id) {
        $parent = Category::find($request->parent_id);
        if ($parent && $parent->parent_id) {
            return response()->json([
                'error' => '❌ Dont create subcategory in subcategory (only 2 levels allowed).'
            ], 422);
        }
    }
        $category = Category::create($request->all());
        return response()->json($category, 201);
    }
        
    public function update(Request $request, $id)
    {
        // Tìm category theo ID
        $category = Category::find($id);
        
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Nếu có truyền parent_id, kiểm tra nó không phải là con của một category khác
        $parentId = $request->input('parent_id');
        if ($parentId) {
            if ($parentId == $id) {
                return response()->json(['error' => '❌ Category can not become a father of itself'], 422);
            }

            $parent = Category::find($parentId);
            if (!$parent) {
                return response()->json(['error' => '❌ parent_id not exist'], 422);
            }

            if ($parent->parent_id) {
                return response()->json([
                    'error' => '❌ Category can not become a child of a subcategory (only 2 levels allowed).'
                ], 422);
            }
        }

        // Cập nhật thông tin nếu hợp lệ
        return $this->updateIfChanged($category, $request->all(), 'Category');
    }

    public function destroy($id)
    {
        // Delete a category
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        $courseCount = $category->courses()->count();
        if ($courseCount > 0) {
            return response()->json(['error' => '❌ Category has courses, cannot be deleted.'], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
    
    public function topCategories()
    {
        $topCategories = Category::withCount('courses')
            ->orderByDesc('courses_count')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topCategories
        ]);
    }
}
