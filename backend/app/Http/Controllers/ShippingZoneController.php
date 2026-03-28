<?php

namespace App\Http\Controllers;

use App\Models\ShippingZone;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    /**
     * Lấy danh sách khu vực vận chuyển (phân trang + tìm kiếm)
     */
    public function index(Request $request)
    {
        $query = ShippingZone::query();

        // Tìm kiếm theo tên hoặc tỉnh/thành
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('provinces', 'like', "%{$search}%");
            });
        }

        // Sắp xếp theo ưu tiên giảm dần
        $query->orderByDesc('priority');

        $perPage = $request->input('per_page', 10);
        $zones = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $zones,
        ]);
    }

    /**
     * Tạo khu vực vận chuyển mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'provinces'           => 'nullable|array',
            'provinces.*'         => 'string|max:255',
            'shipping_fee'        => 'required|integer|min:0',
            'free_ship_threshold' => 'nullable|integer|min:0',
            'delivery_time'       => 'nullable|string|max:50',
            'priority'            => 'nullable|integer|min:0|max:999',
            'is_active'           => 'boolean',
        ]);

        // Default
        $validated['priority']  = $validated['priority'] ?? 50;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $zone = ShippingZone::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tạo khu vực vận chuyển thành công!',
            'data'    => $zone,
        ], 201);
    }

    /**
     * Cập nhật khu vực vận chuyển
     */
    public function update(Request $request, $id)
    {
        $zone = ShippingZone::find($id);

        if (!$zone) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy khu vực!',
            ], 404);
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'provinces'           => 'nullable|array',
            'provinces.*'         => 'string|max:255',
            'shipping_fee'        => 'required|integer|min:0',
            'free_ship_threshold' => 'nullable|integer|min:0',
            'delivery_time'       => 'nullable|string|max:50',
            'priority'            => 'nullable|integer|min:0|max:999',
            'is_active'           => 'boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật khu vực thành công!',
            'data'    => $zone,
        ]);
    }

    /**
     * Xóa khu vực vận chuyển
     */
    public function destroy($id)
    {
        $zone = ShippingZone::find($id);

        if (!$zone) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy khu vực!',
            ], 404);
        }

        $zone->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã xóa khu vực vận chuyển!',
        ]);
    }

    /**
     * Lấy danh sách khu vực đang hoạt động (cho App/Web)
     */
    public function activeZones()
    {
        $zones = ShippingZone::where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $zones,
        ]);
    }
}
