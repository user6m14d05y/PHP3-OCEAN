<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStaffController extends Controller
{
    /**
     * Danh sách tất cả staff (có phân trang/search).
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = Admin::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $admins = $query->orderBy('created_at', 'DESC')->get();

        return response()->json([
            'status' => 'success',
            'data' => $admins,
            'total' => $admins->count()
        ]);
    }

    /**
     * Tạo nhân sự mới.
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,staff,seller',
        ]);

        $admin = Admin::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã tạo tài khoản nhân sự mới thành công!',
            'data' => $admin
        ], 201);
    }

    /**
     * Cập nhật thông tin nhân sự.
     */
    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản!'], 404);
        }

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $id . ',admin_id',
            'role' => 'required|in:admin,staff,seller',
        ]);

        $admin->update($request->only('full_name', 'email', 'role'));

        if ($request->filled('password')) {
            $admin->password = $request->password;
            $admin->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật thông tin nhân sự thành công!'
        ]);
    }

    /**
     * Cập nhật role của nhân sự.
     */
    public function updateRole(Request $request, $id)
    {
        $role = $request->input('role');
        $allowedRoles = ['admin', 'staff', 'seller'];

        if (!in_array($role, $allowedRoles)) {
            return response()->json(['status' => 'error', 'message' => 'Role không hợp lệ!'], 422);
        }

        $admin = Admin::find($id);
        if (!$admin) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản!'], 404);
        }

        // Không cho phép tự đổi role chính mình
        $currentUser = auth('admin')->user();
        if ($currentUser && $currentUser->admin_id == $id) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không thể tự đổi role của chính mình!'], 403);
        }

        $admin->update(['role' => $role]);

        return response()->json([
            'status' => 'success',
            'message' => "Đã phân quyền thành '{$role}' thành công!"
        ]);
    }

    /**
     * Xóa nhân sự.
     */
    public function destroy($id)
    {
        $admin = Admin::find($id);
        if (!$admin) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản!'], 404);
        }

        // Không cho phép tự xóa chính mình
        $currentUser = auth('admin')->user();
        if ($currentUser && $currentUser->admin_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể tự xóa tài khoản của chính mình!'
            ], 403);
        }

        $admin->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa tài khoản nhân sự thành công!'
        ]);
    }
}
