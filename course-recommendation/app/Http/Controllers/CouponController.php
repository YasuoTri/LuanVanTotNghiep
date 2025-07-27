<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $payment=Payment::where('coupon_id', $id)->where("status","completed")->get();
        if ($payment->count() > 0) {
            return response()->json(['message' => 'Cannot update coupon with existing payments'], 400);
        }
          // Kiểm tra số lượng coupon hiệu lực trong khoảng thời gian giao nhau
        // $startDate = $request->start_date;
        // $endDate = $request->end_date;

        // $overlapCoupons = Coupon::where('course_id', $coupon->course->id)
        //     ->where('is_active', 1)
        //     ->where(function($query) use ($startDate, $endDate) {
        //         $query->where(function($q) use ($startDate, $endDate) {
        //             $q->where('start_date', '<=', $endDate)
        //               ->where('end_date', '>=', $startDate);
        //         });
        //     })
        //     ->count();

        // if ($overlapCoupons >= 3) {
        //     return response()->json(['message' => 'Only up to 3 active coupons are allowed for the course during overlapping time periods.'], 422);
        // }
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
        $payment = Payment::where('coupon_id', $id)->where('status',"completed")->first();
        if ($payment) {
            return response()->json(['message' => 'Cannot delete coupon with existing payments'], 400);
        }
        $coupon->delete();
        return response()->json(['message' => 'Coupon deleted successfully']);
    }
    
   public function createCoupon(Request $request)
{
    try {
        $user = Auth::user();

        // Kiểm tra quyền
        if (!$user->instructor) {
            return response()->json(['message' => 'Only instructors can create coupons.'], 403);
        }

        // Kiểm tra course có thuộc instructor không
        $course = Course::where('id', $request->course_id)
            ->where('instructor_id', $user->instructor->id)
            ->where('status', '!=', 'banned')
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or not owned by instructor.'], 404);
        }

        // Validate request
        $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('coupons')->where(fn($q) => $q->where('course_id', $course->id))
            ],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => [
                'required',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($request, $course) {
                    $minDiscount = $course->price; // 50% of course price
                    
                    if ($request->discount_type === 'fixed' && $value > $minDiscount) {
                        $fail("Fixed discount can not be more than 100% of the course price ($minDiscount).");
                    }
                    
                    if ($request->discount_type === 'percent' && $value >100) {
                        $fail("Percentage discount can not be more than 100%.");
                    }
                },
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

      

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
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}
public function getCouponsByCourse(Request $request, $course_id)
    {
    $user = Auth::user();
    $isCourseOwn=$user->instructor->courses->contains('id', $course_id);
    if (!$isCourseOwn) {
        return response()->json(['message' => 'You do not have permission to access this course.'], 403);
    }
        try {
            // Query active coupons for the course
            $coupons = Coupon::where('course_id', $course_id)
                ->paginate(10);

            return response()->json($coupons, 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving coupons',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
