<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    /**
     * Admin: Danh sách tất cả mã giảm giá (kèm categories + lượt dùng)
     */
    public function index()
    {
        $coupons = Coupon::with(['categories:category_id,name', 'userCoupons'])->get();

        // Thêm thông tin thống kê cho mỗi coupon
        $coupons->each(function ($coupon) {
            $coupon->total_users_used = $coupon->userCoupons->where('used_count', '>', 0)->count();
            $coupon->category_ids = $coupon->categories->pluck('category_id');
        });

        return response()->json([
            'status' => 'success',
            'data' => $coupons
        ]);
    }

    /**
     * Admin: Tạo mã giảm giá mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code',
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,category_id',
            'send_email' => 'boolean',
        ]);

        $coupon = Coupon::create($request->except(['category_ids', 'send_email']));

        // Sync danh mục áp dụng
        if ($request->has('category_ids') && is_array($request->category_ids)) {
            $coupon->categories()->sync($request->category_ids);
        }

        // Gửi email thông báo cho khách hàng
        if ($request->input('send_email')) {
            $emailCount = $this->sendNewCouponEmails($coupon);
            return response()->json([
                'status' => 'success',
                'message' => "Mã giảm giá đã được tạo thành công! Đã gửi email cho {$emailCount} khách hàng.",
                'data' => $coupon->load('categories:category_id,name')
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mã giảm giá đã được tạo thành công',
            'data' => $coupon->load('categories:category_id,name')
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        //
    }

    /**
     * Admin: Cập nhật mã giảm giá
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $request->validate([
            'code' => 'required|string|max:20|unique:coupons,code,' . $id,
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0',
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,category_id',
        ]);

        $coupon->update($request->except(['category_ids']));

        // Sync lại danh mục áp dụng
        if ($request->has('category_ids')) {
            $coupon->categories()->sync($request->category_ids ?? []);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật mã giảm giá thành công!',
            'data' => $coupon->load('categories:category_id,name')
        ]);
    }

    /**
     * Admin: Xóa mã giảm giá (soft delete)
     */
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        $coupon->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa mã giảm giá thành công!'
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá công khai cho khách hàng
     */
    public function getPublicCoupons()
    {
        $now = now();
        $coupons = Coupon::where('is_public', true)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
            })
            ->with('categories:category_id,name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $coupons
        ]);
    }

    /**
     * Khách hàng lưu mã giảm giá
     */
    public function saveCoupon(Request $request)
    {
        // Ưu tiên guard 'api' (bảng users), fallback sang 'admin' (bảng admins)
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            $adminUser = auth('admin')->user();
            $userId = $adminUser->getKey();
        }
        
        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để lưu mã giảm giá!'
            ], 401);
        }

        $couponId = $request->input('coupon_id');
        $coupon = Coupon::find($couponId);

        if (!$coupon || !$coupon->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn!'
            ], 404);
        }

        // Kiểm tra xem đã lưu chưa
        $userCoupon = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->first();

        if ($userCoupon) {
            return response()->json([
                'status' => 'info',
                'message' => 'Bạn đã lưu mã giảm giá này rồi!'
            ]);
        }

        UserCoupon::create([
            'user_id' => $userId,
            'coupon_id' => $couponId,
            'is_saved' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu mã giảm giá thành công!'
        ]);
    }

    /**
     * Lấy danh sách mã giảm giá của tôi (đã lưu)
     */
    public function getUserCoupons()
    {
        $user = auth('api')->user();
        $userId = $user ? $user->user_id : null;

        if (!$userId && auth('admin')->check()) {
            $userId = auth('admin')->user()->getKey();
        }

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập!'
            ], 401);
        }

        // Lấy các user_coupons kèm thông tin coupon
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = UserCoupon::with('coupon');
        $userCoupons = $query->where('user_id', $userId)
            ->where('is_saved', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $userCoupons->pluck('coupon')
        ]);
    }

    /**
     * Admin: Xem danh sách users đã dùng coupon cụ thể
     */
    public function getCouponUsages($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã giảm giá!'
            ], 404);
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = UserCoupon::with('user:user_id,full_name,email,phone,avatar_url');
        $usages = $query->where('coupon_id', $id)
            ->orderByDesc('used_count')
            ->get()
            ->map(function ($uc) {
                return [
                    'user_id' => $uc->user_id,
                    'full_name' => $uc->user->full_name ?? 'N/A',
                    'email' => $uc->user->email ?? '',
                    'phone' => $uc->user->phone ?? '',
                    'avatar_url' => $uc->user->avatar_url ?? null,
                    'used_count' => $uc->used_count,
                    'is_saved' => $uc->is_saved,
                    'saved_at' => $uc->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'used_count' => $coupon->used_count,
                    'usage_limit' => $coupon->usage_limit,
                ],
                'usages' => $usages,
                'total_saved' => $usages->count(),
                'total_used' => $usages->filter(function($u) { return $u['used_count'] > 0; })->count(),
            ]
        ]);
    }

    /**
     * Gửi email thông báo mã giảm giá mới cho tất cả khách hàng (đồng bộ)
     */
    private function sendNewCouponEmails(Coupon $coupon): int
    {
        try {
            $emailUser = env('EMAIL_USER');
            $emailPass = env('EMAIL_PASS');

            if (!$emailUser || !$emailPass) {
                Log::warning('Coupon email: EMAIL_USER hoặc EMAIL_PASS chưa cấu hình.');
                return 0;
            }

            // Lấy danh sách users có email
            $users = User::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('deleted_at')
                ->select('email', 'full_name')
                ->get();

            if ($users->isEmpty()) {
                return 0;
            }

            // Tạo SMTP transport
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                'smtp.gmail.com',
                587,
                false
            );
            $transport->setUsername($emailUser);
            $transport->setPassword($emailPass);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $sentCount = 0;

            foreach ($users as $user) {
                try {
                    $htmlBody = $this->buildCouponEmailHtml($coupon, $user->full_name ?? 'Quý khách');

                    $emailMessage = (new \Symfony\Component\Mime\Email())
                        ->from($emailUser)
                        ->to($user->email)
                        ->subject('🎉 Mã giảm giá mới từ Ocean Store — ' . $coupon->code)
                        ->html($htmlBody);

                    $mailer->send($emailMessage);
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("Coupon email failed for {$user->email}: " . $e->getMessage());
                }
            }

            return $sentCount;
        } catch (\Exception $e) {
            Log::error('Coupon email system error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build HTML email template thông báo mã giảm giá mới
     */
    private function buildCouponEmailHtml(Coupon $coupon, string $customerName): string
    {
        // Format giá trị giảm
        $valueText = match ($coupon->type) {
            'percent' => $coupon->value . '%',
            'free_ship' => number_format($coupon->value, 0, ',', '.') . 'đ (Freeship)',
            default => number_format($coupon->value, 0, ',', '.') . 'đ',
        };

        $typeLabel = match ($coupon->type) {
            'percent' => 'Giảm phần trăm',
            'free_ship' => 'Miễn phí vận chuyển',
            default => 'Giảm giá cố định',
        };

        // Thông tin thêm
        $extraInfo = '';
        if ($coupon->min_order_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Đơn tối thiểu</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . number_format($coupon->min_order_value, 0, ',', '.') . 'đ</td></tr>';
        }
        if ($coupon->type === 'percent' && $coupon->max_discount_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Giảm tối đa</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . number_format($coupon->max_discount_value, 0, ',', '.') . 'đ</td></tr>';
        }
        if ($coupon->end_date) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Hết hạn</td><td style="padding: 6px 0; color: #e53e3e; font-size: 13px; font-weight: 600; text-align: right;">' . date('d/m/Y H:i', strtotime($coupon->end_date)) . '</td></tr>';
        }

        $categoriesText = '';
        $coupon->load('categories');
        if ($coupon->categories->isNotEmpty()) {
            $catNames = $coupon->categories->pluck('name')->implode(', ');
            $categoriesText = '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Áp dụng danh mục</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . htmlspecialchars($catNames) . '</td></tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin: 0; padding: 0; background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f0f2f5; padding: 40px 20px;">
                <tr><td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.08);">
                        <!-- Header -->
                        <tr><td style="background: linear-gradient(135deg, #0288d1 0%, #03a9f4 100%); padding: 28px 32px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 20px; margin: 0; font-weight: 600;">🎁 Mã Giảm Giá Mới!</h1>
                            <p style="color: rgba(255,255,255,0.85); font-size: 13px; margin: 6px 0 0;">Ocean Store gửi tặng bạn</p>
                        </td></tr>

                        <!-- Body -->
                        <tr><td style="padding: 32px 32px 24px;">
                            <p style="color: #1a1a2e; font-size: 15px; margin: 0 0 20px; line-height: 1.5;">Xin chào <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Chúng tôi vừa tạo mã giảm giá đặc biệt dành cho bạn. Hãy sử dụng ngay nhé!</p>

                            <!-- Coupon Code Box -->
                            <div style="background: linear-gradient(135deg, #fff8e1 0%, #fff3e0 100%); border: 2px dashed #ff9800; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
                                <p style="color: #e65100; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px;">' . htmlspecialchars($typeLabel) . '</p>
                                <p style="color: #d84315; font-size: 32px; font-weight: 800; margin: 0 0 4px; font-family: \'Courier New\', monospace; letter-spacing: 3px;">' . htmlspecialchars($coupon->code) . '</p>
                                <p style="color: #2e7d32; font-size: 22px; font-weight: 700; margin: 8px 0 0;">Giảm ' . $valueText . '</p>
                            </div>

                            <!-- Info Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #e5e7eb; margin-bottom: 24px;">
                                ' . $extraInfo . $categoriesText . '
                            </table>

                            <!-- CTA -->
                            <div style="text-align: center;">
                                <a href="http://localhost:3000" style="display: inline-block; background: linear-gradient(135deg, #0288d1, #03a9f4); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">Mua sắm ngay</a>
                            </div>
                        </td></tr>

                        <!-- Footer -->
                        <tr><td style="background: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="color: #9ca3af; font-size: 11px; margin: 0; text-align: center; line-height: 1.5;">
                                © ' . date('Y') . ' Ocean Fashion. All rights reserved.<br>
                                Email này được gửi tự động, vui lòng không trả lời.
                            </p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>';
    }
}
