<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

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
    //
}
