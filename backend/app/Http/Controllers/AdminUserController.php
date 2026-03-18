<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /**
     * Danh sách tất cả users (có phân trang).
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'DESC')->get();

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'total' => $users->count()
        ]);
    }

    /**
     * Cập nhật role của user.
     */
    public function updateRole(Request $request, $id)
    {
        $role = $request->input('role');
        $allowedRoles = ['admin', 'staff', 'customer', 'seller'];

        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role không hợp lệ! Chỉ chấp nhận: ' . implode(', ', $allowedRoles)
            ], 422);
        }

        // Không cho phép tự đổi role chính mình
        $currentUser = auth('api')->user();
        if ($currentUser->user_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể đổi role của chính mình!'
            ], 403);
        }

        $affected = DB::update("UPDATE users SET role = ?, updated_at = NOW() WHERE user_id = ?", [$role, $id]);

        if ($affected === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy user!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật role thành '{$role}' thành công!"
        ]);
    }

    /**
     * Cập nhật status của user (active/inactive/banned).
     */
    public function updateStatus(Request $request, $id)
    {
        $status = $request->input('status');
        $allowedStatuses = ['active', 'inactive', 'banned'];

        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status không hợp lệ! Chỉ chấp nhận: ' . implode(', ', $allowedStatuses)
            ], 422);
        }

        $currentUser = auth('api')->user();
        if ($currentUser->user_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể đổi status của chính mình!'
            ], 403);
        }

        $affected = DB::update("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?", [$status, $id]);

        if ($affected === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy user!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật status thành '{$status}' thành công!"
        ]);
    }
}
