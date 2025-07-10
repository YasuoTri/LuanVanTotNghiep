<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    public function index()
    {
        // Fetch all coupons
        $coupons = Coupon::paginate(10);
        return response()->json($coupons);
    }
    public function show($id)
    {
        // Fetch a single coupon by ID
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }
        return response()->json($coupon);
    }
    public function store(Request $request)
    {
        // Create a new coupon
        $coupon = Coupon::create($request->all());
        return response()->json($coupon, 201);
    }
    public function update(Request $request, $id)
    {
        // Update an existing coupon
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }
        $coupon->fill($request->all());
        if (!$coupon->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
        $coupon->update($request->all());
        return response()->json($coupon);
    }
    public function destroy($id)
    {
        // Delete a coupon
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }
        $coupon->delete();
        return response()->json(['message' => 'Coupon deleted successfully']);
    }
    
    public function createCoupon(Request $request)
{
    $user = Auth::user();

    // Kiểm tra quyền
    if (!$user->instructor) {
        return response()->json(['message' => 'Only instructors can create coupons.'], 403);
    }

    // Kiểm tra course có thuộc instructor không
    $course = Course::where('id', $request->course_id)
        ->where('instructor_id', $user->instructor->id)
        ->first();

    if (!$course) {
        return response()->json(['message' => 'Course not found or not owned by instructor.'], 404);
    }
     // Kiểm tra số lượng coupon hiệu lực trong khoảng thời gian giao nhau
    $startDate = $request->start_date;
    $endDate = $request->end_date;

    $overlapCoupons = Coupon::where('course_id', $course->id)
        ->where('is_active', 1)
        ->where(function($query) use ($startDate, $endDate) {
            $query->where(function($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate);
            });
        })
        ->count();

    if ($overlapCoupons >= 3) {
        return response()->json(['message' => 'Only up to 3 active coupons are allowed for the course during overlapping time periods.'], 422);
    }
    // Tạo coupon
    $coupon = Coupon::create([
        'code' => $request->code,
        'discount_type' => $request->discount_type,
        'discount_value' => $request->discount_value,
        'course_id' => $course->id,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'usage_limit' => $request->usage_limit,
    ]);

    return response()->json(['message' => 'Coupon created successfully', 'data' => $coupon]);
}

}
