<?php

namespace App\Http\Controllers;

use App\Models\InstructorRequest;
use Illuminate\Http\Request;

class InstructorRequestController extends Controller
{
    /**
     * Lấy danh sách instructor requests mới nhất để duyệt
     *
     * @param int $perPage Số lượng bản ghi mỗi trang
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLatestPendingRequests($perPage = 10)
    {
        return InstructorRequest::with(['user', 'admin'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    /**
     * Tìm kiếm instructor requests theo các tiêu chí
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchRequests(Request $request)
    {
        $query = InstructorRequest::with(['user', 'admin']);

        // Tìm kiếm theo tên
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        // Tìm kiếm theo email người dùng
        if ($request->filled('email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->input('email') . '%');
            });
        }

        // Tìm kiếm theo số điện thoại
        if ($request->filled('phone_number')) {
            $query->where('phone_number', 'like', '%' . $request->input('phone_number') . '%');
        }

        // Tìm kiếm theo tổ chức
        if ($request->filled('organization')) {
            $query->where('organization', 'like', '%' . $request->input('organization') . '%');
        }

        // Tìm kiếm theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sắp xếp theo thời gian tạo (mới nhất trước) hoặc theo yêu cầu
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Phân trang
        $perPage = $request->input('per_page', 10);
        return $query->paginate($perPage);
    }
}