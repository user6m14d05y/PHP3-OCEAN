<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    private function getUserId()
    {
        if (Auth::guard('admin')->check()) {
            return Auth::guard('admin')->user()->admin_id; 
        }
        return Auth::guard('api')->check() ? Auth::guard('api')->user()->user_id : null;
    }

    /**
     * Calculate spherical distance between two points in meters using Haversine formula
     */
    private function calculateDistanceDistanceInMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radius of the earth in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c; // Distance in meters

        return $distance;
    }

    public function index(Request $request)
    {
        $attendances = Attendance::orderBy('created_at', 'desc')->paginate(15);
        
        // Load relationships manually if user logic is dynamic, or if they are in admins table.
        // Assuming we look up the full name via the admin or user model.
        // For simplicity, we just return the raw data and let front-end handle or we can join:
        
        foreach ($attendances as $attendance) {
            $admin = \App\Models\Admin::find($attendance->user_id);
            if ($admin) {
                $attendance->user_name = $admin->full_name;
                $attendance->role = $admin->role;
            } else {
                $user = \App\Models\User::find($attendance->user_id);
                $attendance->user_name = $user ? $user->full_name : 'Unknown';
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $attendances
        ]);
    }

    public function checkIn(Request $request)
    {
        $userId = $this->getUserId();
        
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $userIp = $request->ip();
        // Validate Wifi Network
        $storeWifiIps = config('services.store.wifi_ip');
        if ($storeWifiIps) {
            $allowedIps = array_map('trim', explode(',', $storeWifiIps));
            if (!in_array($request->ip(), $allowedIps)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn phải kết nối vào Wi-Fi của cửa hàng để điểm danh!'
                ], 403);
            }
        }

        // ===================================
        // 2. Kiểm tra GPS Location (Tùy chọn)
        // Khoảng cách cho phép: < 500m
        // ===================================
        $storeLat = config('services.store.lat');
        $storeLng = config('services.store.lng');
        $userLat = $request->lat;
        $userLng = $request->lng;

        if (!$userLat || !$userLng) {
            return response()->json(['status' => 'error', 'message' => 'Không thể lấy tọa độ GPS từ thiết bị của bạn!'], 400);
        }

        if ($storeLat && $storeLng) {
            $distance = $this->calculateDistanceDistanceInMeters($storeLat, $storeLng, $userLat, $userLng);

            // Allow 50 meters
            if ($distance > 50) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Vị trí của bạn nằm ngoài phạm vi cửa hàng. Bạn phải ở cách cửa hàng dưới 50m thì camera mới kiểm soát được bạn.'
                ], 400);
            }
        }

        // Check if already checked in today without checking out
        $alreadyCheckedIn = Attendance::where('user_id', $userId)
            ->whereDate('check_in_at', now()->toDateString())
            ->whereNull('check_out_at')
            ->exists();

        if ($alreadyCheckedIn) {
            return response()->json(['status' => 'error', 'message' => 'Bạn đã check-in vào ca làm việc chưa kết thúc!'], 400);
        }

        $attendance = Attendance::create([
            'user_id' => $userId,
            'check_in_at' => now(),
            'ip_address' => $request->ip(),
            'latitude' => $userLat,
            'longitude' => $userLng,
            'note' => $request->note,
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Đã Check-in thành công!', 
            'data' => $attendance
        ]);
    }

    public function checkOut(Request $request)
    {
        $userId = $this->getUserId();
        
        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $attendance = Attendance::where('user_id', $userId)
            ->whereNull('check_out_at')
            ->latest()
            ->first();

        if (!$attendance) {
            return response()->json(['status' => 'error', 'message' => 'Bạn chưa check-in hoặc đã check-out rồi!'], 400);
        }

        $attendance->update([
            'check_out_at' => now(),
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Đã Check-out thành công!'
        ]);
    }
}
