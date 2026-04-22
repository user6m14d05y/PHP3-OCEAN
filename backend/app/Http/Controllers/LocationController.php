<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * API base URL cho GHN (Giao Hàng Nhanh) master data
     */
    private string $apiBaseUrl = 'https://online-gateway.ghn.vn/shiip/public-api/master-data/';
    private string $token = '';

    public function __construct()
    {
        $this->token = config('services.ghn.token');
    }

    /**
     * Lấy danh sách tỉnh/thành phố
     * GET /api/location/provinces
     */
    public function getProvinces()
    {
        $data = Cache::remember('vn_provinces', 86400, function () {
            $response = Http::timeout(10)
                ->withHeaders(['Token' => $this->token])
                ->get($this->apiBaseUrl . 'province');

            if ($response->successful()) {
                $result = $response->json();
                $items = $result['data'] ?? [];

                return collect($items)->map(function ($province) {
                    return [
                        'code' => $province['ProvinceID'],
                        'name' => $province['ProvinceName'],
                        'division_type' => 'tỉnh',
                        'codename' => $province['Code'] ?? '',
                    ];
                })->sortBy('name')->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Lấy danh sách quận/huyện theo mã tỉnh (GHN ProvinceID)
     * GET /api/location/districts/{provinceCode}
     */
    public function getDistricts($provinceCode)
    {
        $data = Cache::remember("vn_districts_{$provinceCode}", 86400, function () use ($provinceCode) {
            $response = Http::timeout(10)
                ->withHeaders(['Token' => $this->token])
                ->get($this->apiBaseUrl . 'district', [
                    'province_id' => (int) $provinceCode,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $items = $result['data'] ?? [];

                return collect($items)->map(function ($district) {
                    return [
                        'code' => $district['DistrictID'],
                        'name' => $district['DistrictName'],
                        'division_type' => 'quận/huyện',
                        'codename' => $district['Code'] ?? '',
                    ];
                })->sortBy('name')->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Lấy danh sách phường/xã theo mã quận (GHN DistrictID)
     * GET /api/location/wards/{districtCode}
     */
    public function getWards($districtCode)
    {
        $data = Cache::remember("vn_wards_{$districtCode}", 86400, function () use ($districtCode) {
            $response = Http::timeout(10)
                ->withHeaders(['Token' => $this->token])
                ->get($this->apiBaseUrl . 'ward', [
                    'district_id' => (int) $districtCode,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $items = $result['data'] ?? [];

                return collect($items)->map(function ($ward) {
                    return [
                        'code' => $ward['WardCode'],
                        'name' => $ward['WardName'],
                        'division_type' => 'phường/xã',
                        'codename' => $ward['WardCode'] ?? '',
                    ];
                })->sortBy('name')->values()->toArray();
            }

            return [];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Tìm kiếm địa điểm theo tên (không hỗ trợ bởi GHN API — dùng local filter)
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

        // Tìm kiếm trong danh sách tỉnh đã cache
        $provinces = Cache::get('vn_provinces', []);
        $results = collect($provinces)->filter(function ($p) use ($keyword) {
            return str_contains(mb_strtolower($p['name']), mb_strtolower($keyword));
        })->values()->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ]);
    }
}

