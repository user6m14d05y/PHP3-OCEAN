<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStatisticsController extends Controller
{
    /**
     * Handle date range filters.
     * 
     * @param Request $request
     * @return array [startDate, endDate]
     */
    private function getDateRange(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $preset = $request->input('preset');

        if ($preset) {
            switch ($preset) {
                case 'today':
                    $startDate = Carbon::today()->startOfDay();
                    $endDate = Carbon::today()->endOfDay();
                    break;
                case '7days':
                    $startDate = Carbon::now()->subDays(6)->startOfDay();
                    $endDate = Carbon::now()->endOfDay();
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(29)->startOfDay();
                    $endDate = Carbon::now()->endOfDay();
                    break;
                case 'this_month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'this_year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate = Carbon::now()->endOfYear();
                    break;
            }
        } else {
            $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(29)->startOfDay();
            $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * Get basic overview statistics cards
     */
    public function getOverview(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);

        // Previous period for comparison
        $diffInDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = clone $startDate;
        $prevStartDate->subDays($diffInDays);
        $prevEndDate = clone $endDate;
        $prevEndDate->subDays($diffInDays);

        // Helpers to calculate
        $validStatuses = ['paid']; // Or determine from fulfillment_status

        $totalRevenueQuery = Order::where(function($q) {
            $q->where('payment_status', 'paid')
              ->orWhere('fulfillment_status', 'completed');
        })->whereBetween('created_at', [$startDate, $endDate]);

        $prevTotalRevenueQuery = Order::where(function($q) {
            $q->where('payment_status', 'paid')
              ->orWhere('fulfillment_status', 'completed');
        })->whereBetween('created_at', [$prevStartDate, $prevEndDate]);

        $totalRevenue = (clone $totalRevenueQuery)->sum('grand_total');
        $prevTotalRevenue = (clone $prevTotalRevenueQuery)->sum('grand_total');
        $revenueChange = $this->calculateChange($prevTotalRevenue, $totalRevenue);

        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $prevTotalOrders = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
        $ordersChange = $this->calculateChange($prevTotalOrders, $totalOrders);

        $totalCustomers = User::where('role', 'customer')
            ->whereBetween('created_at', [$startDate, $endDate])->count();
        $prevTotalCustomers = User::where('role', 'customer')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();
        $customersChange = $this->calculateChange($prevTotalCustomers, $totalCustomers);

        $totalProducts = Product::whereBetween('created_at', [$startDate, $endDate])->count();
        $allProducts = Product::count(); // Usually total products overall is better

        // Today specific
        $todayRevenue = Order::whereDate('created_at', Carbon::today())
            ->where(function($q) {
                $q->where('payment_status', 'paid')->orWhere('fulfillment_status', 'completed');
            })->sum('grand_total');
            
        $todayOrders = Order::whereDate('created_at', Carbon::today())->count();
        $pendingOrders = Order::whereIn('fulfillment_status', ['pending', 'processing'])
            ->whereBetween('created_at', [$startDate, $endDate])->count();
        $cancelledOrders = Order::where('fulfillment_status', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => [
                    'value' => $totalRevenue,
                    'isUp' => $revenueChange >= 0,
                    'change' => abs(round($revenueChange, 1)) . '%'
                ],
                'total_orders' => [
                    'value' => $totalOrders,
                    'isUp' => $ordersChange >= 0,
                    'change' => abs(round($ordersChange, 1)) . '%'
                ],
                'total_customers' => [
                    'value' => $totalCustomers,
                    'isUp' => $customersChange >= 0,
                    'change' => abs(round($customersChange, 1)) . '%'
                ],
                'total_products' => [
                    'value' => $allProducts, // total overall
                ],
                'today_revenue' => $todayRevenue,
                'today_orders' => $todayOrders,
                'pending_orders' => $pendingOrders,
                'cancelled_orders' => $cancelledOrders,
            ]
        ]);
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChart(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);
        
        // Group by date
        $revenueData = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(grand_total) as revenue')
        )
        ->where(function($q) {
            $q->where('payment_status', 'paid')
              ->orWhere('fulfillment_status', 'completed');
        })
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('date')
        ->orderBy('date', 'ASC')
        ->get();

        $labels = [];
        $data = [];
        
        $currentDate = clone $startDate;
        $maxDays = $startDate->diffInDays($endDate);

        // Limit charting to prevent huge loops, group by month if > 60 days
        if ($maxDays > 60) {
            $revenueData = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(grand_total) as revenue')
            )
            ->where(function($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('fulfillment_status', 'completed');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->get()->keyBy('month');
            
            $currentMonth = clone $startDate;
            $currentMonth->startOfMonth();
            while($currentMonth <= $endDate) {
                $key = $currentMonth->format('Y-m');
                $labels[] = 'Tháng ' . $currentMonth->format('m/Y');
                $data[] = isset($revenueData[$key]) ? $revenueData[$key]->revenue : 0;
                $currentMonth->addMonth();
            }
        } else {
            $revenueDict = $revenueData->keyBy('date');
            for ($i = 0; $i <= $maxDays; $i++) {
                $dateString = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d/m/Y');
                $data[] = isset($revenueDict[$dateString]) ? $revenueDict[$dateString]->revenue : 0;
                $currentDate->addDay();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Doanh thu',
                        'data' => $data,
                        'borderColor' => '#0288d1',
                        'backgroundColor' => 'rgba(2, 136, 209, 0.1)',
                        'fill' => true,
                        'tension' => 0.4
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get order status chart
     */
    public function getOrderStatusChart(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);

        $statusCounts = Order::select('fulfillment_status', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('fulfillment_status')
            ->get();

        $labels = [];
        $data = [];
        $backgroundColors = [];
        
        $statusMapping = [
            'pending' => ['label' => 'Chờ xác nhận', 'color' => '#ffb74d'],
            'processing' => ['label' => 'Đang xử lý', 'color' => '#64b5f6'],
            'shipping' => ['label' => 'Đang giao hàng', 'color' => '#29b6f6'],
            'shipped' => ['label' => 'Đã giao', 'color' => '#4dd0e1'],
            'delivered' => ['label' => 'Đã nhận hàng', 'color' => '#4db6ac'],
            'completed' => ['label' => 'Hoàn thành', 'color' => '#26a69a'],
            'cancelled' => ['label' => 'Đã hủy', 'color' => '#e57373'],
            'returned' => ['label' => 'Trả hàng', 'color' => '#90a4ae'],
            'failed' => ['label' => 'Giao thất bại', 'color' => '#bdbdbd'],
        ];

        foreach ($statusCounts as $status) {
            $key = $status->fulfillment_status;
            $labels[] = $statusMapping[$key]['label'] ?? ucfirst($key);
            $data[] = $status->total;
            $backgroundColors[] = $statusMapping[$key]['color'] ?? '#cfd8dc';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $backgroundColors
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get top selling products
     */
    public function getTopProducts(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);
        
        // Ensure successful orders only
        $topProducts = OrderItem::select(
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(line_total) as total_revenue')
            )
            ->whereHas('order', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->whereNotIn('fulfillment_status', ['cancelled', 'returned', 'failed']);
            })
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->with(['product' => function($q) {
                $q->select('product_id', 'thumbnail_url');
            }, 'product.variants' => function($q) {
                $q->select('product_id', 'stock');
            }])
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->product_id,
                    'name' => $item->product_name,
                    'image' => $item->product && $item->product->thumbnail_url ? url('storage/' . $item->product->thumbnail_url) : null,
                    'sold' => (int) $item->total_sold,
                    'revenue' => (float) $item->total_revenue,
                    'stock' => $item->product ? $item->product->variants->sum('stock') : 0
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $topProducts
        ]);
    }

    /**
     * Get top customers
     */
    public function getTopCustomers(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);

        $topCustomers = Order::select(
                'user_id',
                'recipient_name',
                'recipient_phone',
                DB::raw('COUNT(order_id) as total_orders'),
                DB::raw('SUM(grand_total) as total_spent'),
                DB::raw('MAX(created_at) as last_order_date')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('fulfillment_status', ['cancelled'])
            ->groupBy('user_id', 'recipient_name', 'recipient_phone')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->with('user')
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->user_id,
                    'name' => $order->recipient_name,
                    'email' => $order->user ? $order->user->email : $order->recipient_phone,
                    'total_orders' => (int) $order->total_orders,
                    'total_spent' => (float) $order->total_spent,
                    'last_order' => Carbon::parse($order->last_order_date)->format('d/m/Y')
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $topCustomers
        ]);
    }

    /**
     * Get detailed revenue report table
     */
    public function getRevenueReport(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRange($request);
        
        $reportData = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(order_id) as total_orders'),
            DB::raw('SUM(grand_total) as total_revenue')
        )
        ->whereNotIn('fulfillment_status', ['cancelled'])
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('date')
        ->orderBy('date', 'DESC')
        ->get();

        $formattedReport = [];
        foreach ($reportData as $row) {
            $formattedReport[] = [
                'date' => Carbon::parse($row->date)->format('d/m/Y'),
                'raw_date' => $row->date,
                'orders' => $row->total_orders,
                'revenue' => $row->total_revenue
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $formattedReport
        ]);
    }

    private function calculateChange($prev, $current)
    {
        if ($prev == 0) {
            return $current > 0 ? 100 : 0;
        }
        return (($current - $prev) / $prev) * 100;
    }
}
