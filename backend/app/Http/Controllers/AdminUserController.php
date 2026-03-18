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
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search', '');

        $query = "SELECT user_id, full_name, email, phone, role, status, created_at, last_login_at FROM users WHERE deleted_at IS NULL";
        $params = [];

        if ($search) {
            $query .= " AND (full_name LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $query .= " ORDER BY created_at DESC";

        $users = DB::select($query, $params);

        return response()->json([
            'status' => 'success',
            'data' => $users,
            'total' => count($users)
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
