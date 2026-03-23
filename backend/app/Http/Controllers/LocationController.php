<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * API base URL cho provinces.open-api.vn
     * Sau khi sáp nhập đơn vị hành chính VN (2025)
     */
    private string $apiBaseUrl = 'https://provinces.open-api.vn/api/';

    /**
     * Lấy danh sách tỉnh/thành phố
     * GET /api/location/provinces
     */
    public function getProvinces()
    {
        $data = Cache::remember('vn_provinces', 86400, function () {
            $response = Http::timeout(10)->get($this->apiBaseUrl, [
                'depth' => 1
            ]);

            if ($response->successful()) {
                return collect($response->json())->map(function ($province) {
                    return [
                        'code' => $province['code'],
                        'name' => $province['name'],
                        'division_type' => $province['division_type'],
                        'codename' => $province['codename'],
                    ];
                })->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Lấy danh sách quận/huyện theo mã tỉnh
     * GET /api/location/districts/{provinceCode}
     */
    public function getDistricts($provinceCode)
    {
        $data = Cache::remember("vn_districts_{$provinceCode}", 86400, function () use ($provinceCode) {
            $response = Http::timeout(10)->get($this->apiBaseUrl . "p/{$provinceCode}", [
                'depth' => 2
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return collect($result['districts'] ?? [])->map(function ($district) {
                    return [
                        'code' => $district['code'],
                        'name' => $district['name'],
                        'division_type' => $district['division_type'],
                        'codename' => $district['codename'],
                    ];
                })->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Lấy danh sách phường/xã theo mã quận
     * GET /api/location/wards/{districtCode}
     */
    public function getWards($districtCode)
    {
        $data = Cache::remember("vn_wards_{$districtCode}", 86400, function () use ($districtCode) {
            $response = Http::timeout(10)->get($this->apiBaseUrl . "d/{$districtCode}", [
                'depth' => 2
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return collect($result['wards'] ?? [])->map(function ($ward) {
                    return [
                        'code' => $ward['code'],
                        'name' => $ward['name'],
                        'division_type' => $ward['division_type'],
                        'codename' => $ward['codename'],
                    ];
                })->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Tìm kiếm địa điểm theo tên
     * GET /api/location/search?q=keyword
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');

        if (strlen($keyword) < 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự.',
            ], 422);
        }

        $response = Http::timeout(10)->get($this->apiBaseUrl . 'p/search/', [
            'q' => $keyword
        ]);

        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json(),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Không thể tìm kiếm địa điểm.',
        ], 500);
    }
}
