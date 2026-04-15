<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductComment;
use App\Models\User;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Contact;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStatisticsController extends Controller
{
    /**
     * Trả về toàn bộ dữ liệu thống kê cho trang Admin Statistics.
     *
     * @param Request $request  ?period=7d|30d|3m|6m|1y (default: 30d)
     */
    public function index(Request $request)
    {
        $period = $request->get('period', '30d');
        $dateRange = $this->getDateRange($period);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Kỳ trước (previous period) để tính % thay đổi
        $prevStart = $dateRange['prev_start'];
        $prevEnd = $dateRange['prev_end'];

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary'            => $this->getSummary($startDate, $endDate, $prevStart, $prevEnd),
                'revenue_over_time'  => $this->getRevenueOverTime($startDate, $endDate, $period),
                'order_status'       => $this->getOrderStatusDistribution($startDate, $endDate),
                'top_products'       => $this->getTopProducts($startDate, $endDate),
                'revenue_by_category'=> $this->getRevenueByCategory($startDate, $endDate),
                'payment_methods'    => $this->getPaymentMethods($startDate, $endDate),
                'new_customers'      => $this->getNewCustomers(),
                'review_stats'       => $this->getReviewStats(),
                'low_stock'          => $this->getLowStock(),
                'recent_orders'      => $this->getRecentOrders(),
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // 1. SUMMARY CARDS (KPI)
    // ────────────────────────────────────────────────────────────

    private function getSummary($start, $end, $prevStart, $prevEnd)
    {
        // --- Current Period ---
        $currentRevenue = $this->paidOrdersQuery()
            ->whereBetween('created_at', [$start, $end])
            ->sum('grand_total');

        $currentOrders = Order::whereNotIn('fulfillment_status', ['cancelled'])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $currentNewCustomers = User::where('role', 'customer')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $currentAOV = $currentOrders > 0 ? round($currentRevenue / $currentOrders) : 0;

        // --- Previous Period ---
        $prevRevenue = $this->paidOrdersQuery()
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('grand_total');

        $prevOrders = Order::whereNotIn('fulfillment_status', ['cancelled'])
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $prevNewCustomers = User::where('role', 'customer')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $prevAOV = $prevOrders > 0 ? round($prevRevenue / $prevOrders) : 0;

        return [
            'revenue' => [
                'value'   => $currentRevenue,
                'display' => number_format($currentRevenue, 0) . ' đ',
                'change'  => $this->calcChange($currentRevenue, $prevRevenue),
            ],
            'orders' => [
                'value'   => $currentOrders,
                'display' => number_format($currentOrders, 0),
                'change'  => $this->calcChange($currentOrders, $prevOrders),
            ],
            'new_customers' => [
                'value'   => $currentNewCustomers,
                'display' => number_format($currentNewCustomers, 0),
                'change'  => $this->calcChange($currentNewCustomers, $prevNewCustomers),
            ],
            'aov' => [
                'value'   => $currentAOV,
                'display' => number_format($currentAOV, 0) . ' đ',
                'change'  => $this->calcChange($currentAOV, $prevAOV),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────
    // 2. REVENUE OVER TIME
    // ────────────────────────────────────────────────────────────

    private function getRevenueOverTime($start, $end, $period)
    {
        $points = [];

        if (in_array($period, ['7d', '30d'])) {
            // Group by DAY
            $current = Carbon::parse($start)->copy();
            $endDate = Carbon::parse($end);

            while ($current->lte($endDate)) {
                $dayStr = $current->format('Y-m-d');
                $revenue = $this->paidOrdersQuery()
                    ->whereDate('created_at', $dayStr)
                    ->sum('grand_total');

                $orderCount = Order::whereNotIn('fulfillment_status', ['cancelled'])
                    ->whereDate('created_at', $dayStr)
                    ->count();

                $points[] = [
                    'label'    => $current->format('d/m'),
                    'revenue'  => (float)$revenue,
                    'orders'   => $orderCount,
                ];
                $current->addDay();
            }
        } else {
            // Group by MONTH
            $current = Carbon::parse($start)->startOfMonth();
            $endDate = Carbon::parse($end);

            while ($current->lte($endDate)) {
                $revenue = $this->paidOrdersQuery()
                    ->whereYear('created_at', $current->year)
                    ->whereMonth('created_at', $current->month)
                    ->sum('grand_total');

                $orderCount = Order::whereNotIn('fulfillment_status', ['cancelled'])
                    ->whereYear('created_at', $current->year)
                    ->whereMonth('created_at', $current->month)
                    ->count();

                $points[] = [
                    'label'    => 'T' . $current->month . '/' . $current->format('y'),
                    'revenue'  => (float)$revenue,
                    'orders'   => $orderCount,
                ];
                $current->addMonth();
            }
        }

        return $points;
    }

    // ────────────────────────────────────────────────────────────
    // 3. ORDER STATUS DISTRIBUTION
    // ────────────────────────────────────────────────────────────

    private function getOrderStatusDistribution($start, $end)
    {
        $statuses = Order::whereBetween('created_at', [$start, $end])
            ->select('fulfillment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('fulfillment_status')
            ->pluck('count', 'fulfillment_status')
            ->toArray();

        $labels = [
            'pending'   => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'shipping'  => 'Đang giao',
            'shipped'   => 'Đã giao',
            'delivered' => 'Đã nhận',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];

        $colors = [
            'pending'   => '#ffa726',
            'confirmed' => '#42a5f5',
            'shipping'  => '#26c6da',
            'shipped'   => '#66bb6a',
            'delivered' => '#29b6f6',
            'completed' => '#26a69a',
            'cancelled' => '#ef5350',
        ];

        $result = [];
        foreach ($labels as $key => $label) {
            $count = $statuses[$key] ?? 0;
            if ($count > 0) {
                $result[] = [
                    'key'   => $key,
                    'label' => $label,
                    'count' => $count,
                    'color' => $colors[$key],
                ];
            }
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────────
    // 4. TOP PRODUCTS (by quantity sold)
    // ────────────────────────────────────────────────────────────

    private function getTopProducts($start, $end)
    {
        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNotIn('orders.fulfillment_status', ['cancelled'])
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('SUM(order_items.line_total) as total_revenue')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id'   => $item->product_id,
                    'name'         => $item->product_name,
                    'quantity'     => (int)$item->total_qty,
                    'revenue'      => (float)$item->total_revenue,
                    'revenue_fmt'  => number_format($item->total_revenue, 0) . ' đ',
                ];
            });
    }

    // ────────────────────────────────────────────────────────────
    // 5. REVENUE BY CATEGORY
    // ────────────────────────────────────────────────────────────

    private function getRevenueByCategory($start, $end)
    {
        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.category_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNotIn('orders.fulfillment_status', ['cancelled'])
            ->select(
                'categories.category_id',
                'categories.name as category_name',
                DB::raw('SUM(order_items.line_total) as total_revenue'),
                DB::raw('SUM(order_items.quantity) as total_qty')
            )
            ->groupBy('categories.category_id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->limit(8)
            ->get()
            ->map(function ($item) {
                return [
                    'category_id'  => $item->category_id,
                    'name'         => $item->category_name,
                    'revenue'      => (float)$item->total_revenue,
                    'revenue_fmt'  => number_format($item->total_revenue, 0) . ' đ',
                    'quantity'     => (int)$item->total_qty,
                ];
            });
    }

    // ────────────────────────────────────────────────────────────
    // 6. PAYMENT METHODS DISTRIBUTION
    // ────────────────────────────────────────────────────────────

    private function getPaymentMethods($start, $end)
    {
        $methods = Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('fulfillment_status', ['cancelled'])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->get();

        $labels = [
            'cod'         => 'COD',
            'vnpay'       => 'VNPay',
            'momo'        => 'MoMo',
            'bank'        => 'Chuyển khoản',
            'pos_cash'    => 'POS - Tiền mặt',
            'pos_card'    => 'POS - Thẻ',
            'pos_transfer'=> 'POS - CK',
        ];

        $colors = [
            'cod'         => '#ffa726',
            'vnpay'       => '#42a5f5',
            'momo'        => '#ec407a',
            'bank'        => '#26a69a',
            'pos_cash'    => '#66bb6a',
            'pos_card'    => '#7e57c2',
            'pos_transfer'=> '#29b6f6',
        ];

        return $methods->map(function ($m) use ($labels, $colors) {
            return [
                'method'      => $m->payment_method,
                'label'       => $labels[$m->payment_method] ?? $m->payment_method,
                'count'       => (int)$m->count,
                'total'       => (float)$m->total,
                'total_fmt'   => number_format($m->total, 0) . ' đ',
                'color'       => $colors[$m->payment_method] ?? '#78909c',
            ];
        });
    }

    // ────────────────────────────────────────────────────────────
    // 7. NEW CUSTOMERS (last 6 months)
    // ────────────────────────────────────────────────────────────

    private function getNewCustomers()
    {
        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $count = User::where('role', 'customer')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $result[] = [
                'label' => 'T' . $month->month,
                'count' => $count,
            ];
        }
        return $result;
    }

    // ────────────────────────────────────────────────────────────
    // 8. REVIEW STATS
    // ────────────────────────────────────────────────────────────

    private function getReviewStats()
    {
        $total = ProductComment::count();
        $avgRating = ProductComment::avg('rating') ?? 0;

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = ProductComment::where('rating', $star)->count();
            $distribution[] = [
                'star'    => $star,
                'count'   => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'total'        => $total,
            'avg_rating'   => round($avgRating, 1),
            'distribution' => $distribution,
        ];
    }

    // ────────────────────────────────────────────────────────────
    // 9. LOW STOCK ALERT
    // ────────────────────────────────────────────────────────────

    private function getLowStock()
    {
        return ProductVariant::with('product:product_id,name,thumbnail_url')
            ->whereColumn('stock', '<=', 'safety_stock')
            ->where('status', 'active')
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($v) {
                return [
                    'variant_id'   => $v->variant_id,
                    'product_name' => $v->product->name ?? 'N/A',
                    'variant_name' => $v->variant_name,
                    'sku'          => $v->sku,
                    'stock'        => $v->stock,
                    'safety_stock' => $v->safety_stock,
                ];
            });
    }

    // ────────────────────────────────────────────────────────────
    // 10. RECENT ORDERS
    // ────────────────────────────────────────────────────────────

    private function getRecentOrders()
    {
        return Order::with(['user:user_id,full_name', 'items'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {
                $userName = $order->user->full_name ?? $order->recipient_name ?? 'Khách lẻ';

                $statusLabels = [
                    'pending'   => 'Chờ xử lý',
                    'confirmed' => 'Đã xác nhận',
                    'shipping'  => 'Đang giao',
                    'shipped'   => 'Đã giao',
                    'delivered' => 'Đã nhận',
                    'completed' => 'Hoàn thành',
                    'cancelled' => 'Đã hủy',
                ];

                $paymentLabels = [
                    'unpaid'    => 'Chưa TT',
                    'paid'      => 'Đã TT',
                    'refunded'  => 'Hoàn tiền',
                ];

                return [
                    'order_id'          => $order->order_id,
                    'order_code'        => $order->order_code,
                    'customer'          => $userName,
                    'items_count'       => $order->items->count(),
                    'grand_total'       => number_format($order->grand_total, 0) . ' đ',
                    'grand_total_raw'   => (float)$order->grand_total,
                    'payment_method'    => $order->payment_method,
                    'payment_status'    => $paymentLabels[$order->payment_status] ?? $order->payment_status,
                    'fulfillment_status'=> $statusLabels[$order->fulfillment_status] ?? $order->fulfillment_status,
                    'status_key'        => $order->fulfillment_status,
                    'created_at'        => Carbon::parse($order->created_at)->format('d/m/Y H:i'),
                ];
            });
    }

    // ════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ════════════════════════════════════════════════════════════

    /**
     * Base query: chỉ tính đơn đã thanh toán hoặc hoàn thành.
     */
    private function paidOrdersQuery()
    {
        return Order::where(function ($q) {
            $q->where('payment_status', 'paid')
              ->orWhere('fulfillment_status', 'completed');
        });
    }

    /**
     * Tính % thay đổi giữa current và previous.
     */
    private function calcChange($current, $previous): array
    {
        if ($previous == 0) {
            return [
                'percent' => $current > 0 ? 100 : 0,
                'is_up'   => $current >= 0,
            ];
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);
        return [
            'percent' => abs($percent),
            'is_up'   => $percent >= 0,
        ];
    }

    /**
     * Chuyển đổi period string → date range + previous period.
     */
    private function getDateRange(string $period): array
    {
        $end = Carbon::now()->endOfDay();

        switch ($period) {
            case '7d':
                $start = Carbon::now()->subDays(6)->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                $prevStart = $prevEnd->copy()->subDays(6)->startOfDay();
                break;
            case '3m':
                $start = Carbon::now()->subMonths(3)->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                $prevStart = $prevEnd->copy()->subMonths(3)->startOfDay();
                break;
            case '6m':
                $start = Carbon::now()->subMonths(6)->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                $prevStart = $prevEnd->copy()->subMonths(6)->startOfDay();
                break;
            case '1y':
                $start = Carbon::now()->subYear()->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                $prevStart = $prevEnd->copy()->subYear()->startOfDay();
                break;
            default: // 30d
                $start = Carbon::now()->subDays(29)->startOfDay();
                $prevEnd = $start->copy()->subSecond();
                $prevStart = $prevEnd->copy()->subDays(29)->startOfDay();
                break;
        }

        return [
            'start'      => $start,
            'end'        => $end,
            'prev_start' => $prevStart,
            'prev_end'   => $prevEnd,
        ];
    }
}
