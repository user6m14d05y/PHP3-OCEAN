import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'main_wrapper.dart';
import 'address_screen.dart';
const String kBaseUrl = 'http://10.0.2.2:8383/api';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  int selectedPayment = 0; // 0: COD, 1: VNPay, 2: MoMo
  Map<String, dynamic>? defaultAddress;
  List<dynamic> cartItems = [];
  int subtotal = 0;
  int shippingFee = 35000;
  bool isLoading = true;
  // Coupon
  Map<String, dynamic>? appliedCoupon;
  int discountAmount = 0;
  final _couponCtrl = TextEditingController();
  bool _isApplyingCoupon = false;

  @override
  void initState() {
    super.initState();
    fetchCheckoutData();
  }

  Future<void> fetchCheckoutData() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      if (token == null) return;

      // Lấy Addresses
      final addressRes = await http.get(
        Uri.parse('$kBaseUrl/profile/addresses'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      if (addressRes.statusCode == 200) {
        final data = jsonDecode(addressRes.body)['data'] as List?;
        if (data != null && data.isNotEmpty) {
          defaultAddress = data.firstWhere((a) => a['is_default'] == 1, orElse: () => data.first);
        }
      }

      // Lấy Giỏ hàng
      final cartRes = await http.get(
        Uri.parse('$kBaseUrl/cart'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      if (cartRes.statusCode == 200) {
        final cData = jsonDecode(cartRes.body)['data'];
        if (cData != null) {
          cartItems = (cData['items'] as List?)?.where((item) => item['selected'] == 1 || item['selected'] == true).toList() ?? [];
          subtotal = cData['total_price'] ?? 0;
        }
      }

      // Tinh phi GHN
      await _calculateShippingFee();

      setState(() {
        isLoading = false;
      });
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  Future<void> _calculateShippingFee() async {
    if (defaultAddress == null || defaultAddress!['district_code'] == null || defaultAddress!['ward_code'] == null) {
      setState(() { shippingFee = 0; });
      return;
    }
    try {
      final response = await http.get(
        Uri.parse('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee?service_type_id=2&to_district_id=${defaultAddress!['district_code']}&to_ward_code=${defaultAddress!['ward_code']}&height=15&length=15&weight=500&width=15&insurance_value=0'),
        headers: {
          'Token': dotenv.env['TOKEN_GHN'] ?? '',
          'ShopId': '5881673',
          'Content-Type': 'application/json'
        }
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        setState(() {
          shippingFee = data['data']?['total'] ?? 0;
        });
      } else {
        setState(() { shippingFee = 0; });
      }
    } catch (_) {
      setState(() { shippingFee = 0; });
    }
  }

  Future<void> _applyCoupon() async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty) return;
    setState(() => _isApplyingCoupon = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      final res = await http.get(
        Uri.parse('$kBaseUrl/coupons/public'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      if (res.statusCode == 200) {
        final List coupons = jsonDecode(res.body)['data'] ?? [];
        final coupon = coupons.firstWhere(
          (c) => c['code'].toString().toLowerCase() == code.toLowerCase() && c['is_active'] == true,
          orElse: () => null,
        );
        if (coupon == null) {
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mã giảm giá không hợp lệ hoặc đã hết hạn!'), backgroundColor: Colors.red));
        } else {
          final minOrder = num.tryParse(coupon['min_order_value']?.toString() ?? '0') ?? 0;
          if (subtotal < minOrder) {
            if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Đơn hàng tối thiểu ${_formatPrice(minOrder)} để dùng mã này!'), backgroundColor: Colors.orange));
          } else {
            int discount = 0;
            if (coupon['type'] == 'percent') {
              discount = (subtotal * num.parse(coupon['value'].toString()) / 100).round();
              final maxDisc = num.tryParse(coupon['max_discount_value']?.toString() ?? '0') ?? 0;
              if (maxDisc > 0 && discount > maxDisc) discount = maxDisc.toInt();
            } else if (coupon['type'] == 'fixed') {
              discount = num.parse(coupon['value'].toString()).toInt();
            } else if (coupon['type'] == 'free_ship') {
              discount = shippingFee;
            }
            setState(() { appliedCoupon = coupon; discountAmount = discount; });
            if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Áp dụng mã thành công! Giảm ${_formatPrice(discount)}'), backgroundColor: Colors.green));
          }
        }
      }
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kiểm tra mã!'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isApplyingCoupon = false);
    }
  }

  void _removeCoupon() {
    setState(() { appliedCoupon = null; discountAmount = 0; _couponCtrl.clear(); });
  }

  Future<void> placeOrder() async {
    if (defaultAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng thêm địa chỉ nhận hàng!')));
      return;
    }
    if (cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Giỏ hàng trống!')));
      return;
    }

    showDialog(context: context, barrierDismissible: false, builder: (context) => const Center(child: CircularProgressIndicator()));

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');

      String pm = 'cod';
      if (selectedPayment == 1) pm = 'vnpay';
      if (selectedPayment == 2) pm = 'momo';

      final response = await http.post(
        Uri.parse('$kBaseUrl/profile/orders'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token'
        },
        body: jsonEncode({
          'address_id': defaultAddress!['address_id'],
          'payment_method': pm,
          'shipping_fee': shippingFee,
          if (appliedCoupon != null) 'coupon_code': appliedCoupon!['code'],
        }),
      );

      final data = jsonDecode(response.body);

      if (mounted) Navigator.pop(context); // hide loading

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đặt hàng thành công!'), backgroundColor: Colors.green));
          // Chuyển về màn trạng thái đơn hàng (tab 3)
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute(builder: (context) => const MainWrapper(initialIndex: 3)),
            (route) => false,
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message'] ?? 'Lỗi đặt hàng'), backgroundColor: Colors.red));
        }
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối máy chủ!'), backgroundColor: Colors.red));
      }
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.');
      return '$formatted đ';
    } catch (_) {
      return price.toString();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return const Scaffold(backgroundColor: Color(0xFFF8FAFC), body: Center(child: CircularProgressIndicator()));
    }

    final grandTotal = (subtotal + shippingFee - discountAmount).clamp(0, double.maxFinite).toInt();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Thanh toán', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F172A), fontSize: 18)),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(icon: const Icon(Icons.arrow_back, color: Color(0xFF0EA5E9)), onPressed: () => Navigator.pop(context)),
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 120),
            child: Column(
              children: [
                _buildAddressBox(),
                const SizedBox(height: 0),
                _buildPaymentBox(),
                const SizedBox(height: 0),
                _buildCouponBox(),
                _buildOrderSummary(),
              ],
            ),
          ),
          // Sticky Bottom Bar
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, -4))],
              ),
              child: SafeArea(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tổng cộng', style: TextStyle(fontSize: 13, color: Color(0xFF475569))),
                        Text(_formatPrice(grandTotal), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF0284C7))),
                      ],
                    ),
                    ElevatedButton(
                      onPressed: placeOrder,
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                        backgroundColor: const Color(0xFF0EA5E9),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30))
                      ),
                      child: const Text('Thanh toán', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAddressBox() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.location_on_outlined, color: Color(0xFF0EA5E9), size: 20),
                  SizedBox(width: 8),
                  Text('Địa chỉ nhận hàng', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
                ],
              ),
              GestureDetector(
                onTap: () async {
                  final selected = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => const AddressScreen(isSelecting: true)),
                  );
                  if (selected != null) {
                    setState(() {
                      defaultAddress = selected;
                      isLoading = true;
                    });
                    await _calculateShippingFee();
                    setState(() { isLoading = false; });
                  }
                },
                child: Text(defaultAddress != null ? 'Thay đổi' : 'Thêm mới', style: TextStyle(fontWeight: FontWeight.w600, color: Colors.blue.shade600, fontSize: 13)),
              )
            ],
          ),
          const SizedBox(height: 12),
          if (defaultAddress != null) ...[
            Text(defaultAddress!['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
            const SizedBox(height: 4),
            Text(defaultAddress!['phone'] ?? '', style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
            const SizedBox(height: 4),
            Text('${defaultAddress!['address_line']}, ${defaultAddress!['ward']}, ${defaultAddress!['district']}, ${defaultAddress!['province']}', style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.5)),
          ] else ...[
            const Text('Bạn chưa có địa chỉ giao hàng. Vui lòng thêm.', style: TextStyle(color: Colors.red)),
          ]
        ],
      ),
    );
  }

  Widget _buildPaymentBox() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.payment_outlined, color: Color(0xFF0EA5E9), size: 20),
              SizedBox(width: 8),
              Text('Phương thức thanh toán', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
            ],
          ),
          const SizedBox(height: 12),
          _buildPaymentOption(0, Icons.delivery_dining_outlined, 'Thanh toán khi nhận hàng (COD)', 'Trả tiền mặt cho người giao'),
          _buildPaymentOption(1, Icons.credit_card, 'Thanh toán VNPay', 'Chuyển khoản / Thẻ ngân hàng ATM'),
          _buildPaymentOption(2, Icons.account_balance_wallet_outlined, 'Thanh toán MoMo', 'Ví điện tử Momo'),
        ],
      ),
    );
  }

  Widget _buildPaymentOption(int index, IconData icon, String title, String subtitle) {
    final isSelected = selectedPayment == index;
    return GestureDetector(
      onTap: () => setState(() => selectedPayment = index),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0F9FF) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: isSelected ? const Color(0xFF38BDF8) : const Color(0xFFE2E8F0)),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(8)),
              child: Icon(icon, color: const Color(0xFF475569), size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0F172A))),
                  const SizedBox(height: 4),
                  Text(subtitle, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                ],
              ),
            ),
            Icon(isSelected ? Icons.radio_button_checked : Icons.radio_button_off, color: isSelected ? const Color(0xFF0284C7) : const Color(0xFFCBD5E1), size: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderSummary() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Tóm tắt đơn hàng', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
          const SizedBox(height: 16),
          ...cartItems.map((item) {
            String name = item['variant']['variant_name'] ?? item['product']['name'] ?? '';
            String price = _formatPrice(item['line_total']);
            String qty = 'Số lượng: ${item['quantity']}';
            
            String imageUrl = '';
            final rawImage = item['product']['main_image'] ?? item['product']['thumbnail_url'] ?? '';
            if (rawImage.toString().isNotEmpty) {
              imageUrl = rawImage.toString().startsWith('http') ? rawImage : 'http://10.0.2.2:8383/api/image-proxy?path=$rawImage';
            }

            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 40, height: 40,
                    decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(8)),
                    child: imageUrl.isNotEmpty 
                      ? ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(imageUrl, fit: BoxFit.cover, errorBuilder: (_,__,___) => const Icon(Icons.image, color: Colors.grey, size: 20)))
                      : const Icon(Icons.image, color: Colors.grey, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 4),
                        Text(qty, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                      ],
                    ),
                  ),
                  Text(price, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF0284C7))),
                ],
              ),
            );
          }).toList(),
          const Divider(color: Color(0xFFE2E8F0)),
          const SizedBox(height: 8),
          _buildPriceRow('Tạm tính', _formatPrice(subtotal)),
          const SizedBox(height: 8),
          _buildPriceRow('Phí vận chuyển', _formatPrice(shippingFee)),
          if (discountAmount > 0) ...[
            const SizedBox(height: 8),
            _buildPriceRow('Giảm giá (${appliedCoupon?['code'] ?? ''})', '- ${_formatPrice(discountAmount)}', valueColor: Colors.green),
          ],
        ],
      ),
    );
  }

  Widget _buildCouponBox() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(children: [
            Icon(Icons.local_offer_outlined, color: Color(0xFF0EA5E9), size: 20),
            SizedBox(width: 8),
            Text('Mã giảm giá', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
          ]),
          const SizedBox(height: 12),
          if (appliedCoupon == null)
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _couponCtrl,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    hintText: 'Nhập mã giảm giá...',
                    hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                    filled: true,
                    fillColor: const Color(0xFFF8FAFC),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF0EA5E9))),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              ElevatedButton(
                onPressed: _isApplyingCoupon ? null : _applyCoupon,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0EA5E9),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: _isApplyingCoupon
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Áp dụng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              ),
            ])
          else
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(color: Colors.green.withOpacity(0.08), borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.withOpacity(0.3))),
              child: Row(children: [
                const Icon(Icons.check_circle, color: Colors.green, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(appliedCoupon!['code'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green, fontSize: 14)),
                  Text('Giảm ${_formatPrice(discountAmount)}', style: const TextStyle(fontSize: 12, color: Colors.green)),
                ])),
                GestureDetector(
                  onTap: _removeCoupon,
                  child: const Icon(Icons.close, color: Colors.grey, size: 18),
                ),
              ]),
            ),
        ],
      ),
    );
  }

  Widget _buildPriceRow(String label, String value, {Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
        Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: valueColor ?? const Color(0xFF0F172A))),
      ],
    );
  }
}
