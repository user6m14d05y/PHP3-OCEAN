import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'services/auth_service.dart';
import 'screens/login_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final Map<String, dynamic> product;

  const ProductDetailScreen({super.key, required this.product});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  String selectedColor = '';
  String selectedSize = '';
  List<dynamic> comments = [];
  bool isLoadingComments = true;
  Map<String, dynamic> _product = {};
  bool isLoadingDetails = true;

  @override
  void initState() {
    super.initState();
    _product = Map<String, dynamic>.from(widget.product);
    fetchProductDetails();
    fetchComments();
  }

  Future<void> fetchProductDetails() async {
    try {
      final slug = _product['slug'];
      final url = Uri.parse('http://localhost:8383/api/products/slug/$slug');
      final res = await http.get(url, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            _product = data;
            isLoadingDetails = false;
            
            final variants = _product['variants'] as List<dynamic>? ?? [];
            if (variants.isNotEmpty) {
              List<String> colors = [];
              List<String> sizes = [];
              for (var v in variants) {
                if (v['color'] != null && !colors.contains(v['color'].toString())) colors.add(v['color'].toString());
                if (v['size'] != null && !sizes.contains(v['size'].toString())) sizes.add(v['size'].toString());
              }
              if (colors.isNotEmpty) selectedColor = colors.first;
              if (sizes.isNotEmpty) selectedSize = sizes.first;
            }
          });
        }
      } else {
        if (mounted) setState(() => isLoadingDetails = false);
      }
    } catch (_) {
      if (mounted) setState(() => isLoadingDetails = false);
    }
  }

  Future<void> fetchComments() async {
    try {
      final id = _product['product_id'] ?? _product['id'];
      final url = Uri.parse('http://localhost:8383/api/products/$id/comments');
      final res = await http.get(url, headers: {'Accept': 'application/json'});
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) setState(() { comments = data['data'] ?? []; isLoadingComments = false; });
      } else {
        if (mounted) setState(() => isLoadingComments = false);
      }
    } catch (_) {
      if (mounted) setState(() => isLoadingComments = false);
    }
  }
  void _handleActionSelected(String actionStr) async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vui lòng đăng nhập để tiếp tục')),
        );
        Navigator.push(context, MaterialPageRoute(builder: (context) => const LoginScreen()));
      }
      return;
    }
    
    // Tìm Variant ID
    int? variantId;
    final variants = _product['variants'] as List<dynamic>? ?? [];
    for (var v in variants) {
      final colorMatch = (v['color']?.toString() ?? '') == selectedColor;
      final sizeMatch = (v['size']?.toString() ?? '') == selectedSize;
      
      // Nếu product không có color hoặc size thì bỏ qua check tương ứng
      final vColor = v['color']?.toString() ?? '';
      final vSize = v['size']?.toString() ?? '';
      
      bool match = true;
      if (vColor.isNotEmpty && vColor != selectedColor) match = false;
      if (vSize.isNotEmpty && vSize != selectedSize) match = false;
      
      if (match) {
        variantId = v['variant_id'];
        break;
      }
    }

    if (variantId == null && variants.isNotEmpty) {
      variantId = variants.first['variant_id'];
    }

    if (variantId == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Không thể đặt hàng do sản phẩm thiếu dữ liệu Hệ thống (Variants)'), backgroundColor: Colors.red));
      }
      return;
    }

    // Show loading
    showDialog(context: context, barrierDismissible: false, builder: (context) => const Center(child: CircularProgressIndicator()));

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      
      final response = await http.post(
        Uri.parse('http://localhost:8383/api/cart/items'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode({
          'variant_id': variantId,
          'quantity': 1,
        }),
      ).timeout(const Duration(seconds: 15));

      if (mounted) Navigator.pop(context); // Close loading

      final data = jsonDecode(response.body);
      if (response.statusCode == 200 || response.statusCode == 201) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Thêm vào giỏ thành công!'), backgroundColor: Colors.green),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message'] ?? 'Lỗi thêm sản phẩm!'), backgroundColor: Colors.red),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context); // Close loading
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Không kết nối được máy chủ!'), backgroundColor: Colors.red));
      }
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]}.',
          );
      return '$formatted đ';
    } catch (_) {
      return price.toString();
    }
  }

  // Lấy danh sách Color và Size từ variants array (dựa theo backend OceanShop có trường color và size)
  List<String> _getUniqueAttributes(String key) {
    final variants = _product['variants'] as List<dynamic>? ?? [];
    List<String> list = [];
    for (var v in variants) {
      final val = v[key]?.toString() ?? '';
      if (val.isNotEmpty && !list.contains(val)) list.add(val);
    }
    return list;
  }

  @override
  Widget build(BuildContext context) {
    if (isLoadingDetails) {
      return const Scaffold(backgroundColor: Color(0xFFF8FAFC), body: Center(child: CircularProgressIndicator(color: Color(0xFF0EA5E9))));
    }

    // Giá và ảnh mặc định
    dynamic priceRaw = _product['min_price'] ?? (_product['lowest_price_variant'] is Map ? _product['lowest_price_variant']['price'] : 0);
    String rawImage = (_product['thumbnail_url'] ?? '').toString();

    // Tìm variant đang được chọn để update Giá và Ảnh
    final variants = _product['variants'] as List<dynamic>? ?? [];
    for (var v in variants) {
      final vColor = v['color']?.toString() ?? '';
      final vSize = v['size']?.toString() ?? '';
      
      bool match = true;
      if (vColor.isNotEmpty && vColor != selectedColor) match = false;
      if (vSize.isNotEmpty && vSize != selectedSize) match = false;
      
      if (match) {
        if (v['price'] != null) priceRaw = v['price'];
        if (v['image_url'] != null && v['image_url'].toString().isNotEmpty) {
          rawImage = v['image_url'];
        }
        break;
      }
    }

    // Tính giá gốc giả định (cao hơn 15%)
    final double rawValue = double.tryParse(priceRaw.toString()) ?? 0;
    final String oldPrice = _formatPrice(rawValue * 1.15); // Chỉ để minh hoạ layout Figma có 2 giá

    String imageUrl = '';
    if (rawImage.isNotEmpty) {
      if (rawImage.startsWith('http')) imageUrl = rawImage;
      else imageUrl = 'http://localhost:8383/api/image-proxy?path=$rawImage';
    }

    String description = _product['description'] ?? 'Khám phá đại dương sâu thẳm cùng Ocean Pro. Được chế tác với độ chính xác tuyệt đối, đây không chỉ là một chiếc đồng hồ, mà là người đồng hành đáng tin cậy...';
    description = description.replaceAll(RegExp(r'<[^>]*>'), '').replaceAll('&nbsp;', ' ').trim();

    final categoryName = _product['category'] is Map ? (_product['category']['name'] ?? 'PHỤ KIỆN CHUYÊN DỤNG') : 'PHỤ KIỆN CHUYÊN DỤNG';
    
    final listColors = _getUniqueAttributes('color');
    final listSizes = _getUniqueAttributes('size');

    if (selectedSize.isEmpty && listSizes.isNotEmpty) selectedSize = listSizes.first;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Ocean Shop', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0369A1), fontSize: 18)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0EA5E9),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Navigator.pop(context)),
        actions: [
          IconButton(icon: const Icon(Icons.share_outlined), onPressed: () {}),
        ],
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 100), // chừa chổ cho bottom var
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Image Section
                Container(
                  color: Colors.white,
                  child: Stack(
                    children: [
                      imageUrl.isNotEmpty 
                        ? Image.network(imageUrl, width: double.infinity, height: 350, fit: BoxFit.cover, errorBuilder: (_,__,___) => _imagePlaceholder())
                        : _imagePlaceholder(),
                      Positioned(
                        bottom: 16,
                        right: 16,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(12)),
                          child: const Text('1/5', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                        ),
                      )
                    ],
                  ),
                ),
                
                // Content Section
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.only(bottomLeft: Radius.circular(24), bottomRight: Radius.circular(24))
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(categoryName.toUpperCase(), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF0284C7))),
                          const Icon(Icons.favorite_border, color: Color(0xFF94A3B8)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(_product['name'] ?? 'Sản phẩm chưa có tên', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), height: 1.2)),
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(_formatPrice(priceRaw), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF0284C7))),
                          const SizedBox(width: 8),
                          Text(oldPrice, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF94A3B8), decoration: TextDecoration.lineThrough)),
                        ],
                      ),
                      const SizedBox(height: 24),
                      // Features Grid
                      GridView.count(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisCount: 2,
                        childAspectRatio: 3,
                        mainAxisSpacing: 10,
                        crossAxisSpacing: 10,
                        children: [
                          _buildFeatureItem(Icons.water_drop_outlined, 'Chống nước', '300m / 1000ft'),
                          _buildFeatureItem(Icons.settings_outlined, 'Tự động', 'Trữ cót 72h'),
                          _buildFeatureItem(Icons.shield_outlined, 'Bảo hành', '5 năm quốc tế'),
                          _buildFeatureItem(Icons.diamond_outlined, 'Vật liệu', 'Thép không gỉ 316L'),
                        ],
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 8),

                // Options Section
                if (listColors.isNotEmpty || listSizes.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(20),
                    color: Colors.white,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Màu sắc
                        if (listColors.isNotEmpty) ...[
                          const Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Màu sắc', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            runSpacing: 8,
                            children: listColors.map((colorVal) => _buildColorChoice(colorVal)).toList(),
                          ),
                          if (listSizes.isNotEmpty) const SizedBox(height: 24),
                        ],
                        
                        // Kích thước
                        if (listSizes.isNotEmpty) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Kích thước', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                              Text('Hướng dẫn đo size', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.blue.shade600))
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: listSizes.map((sizeVal) => _buildSizeChoice(sizeVal)).toList(),
                          ),
                        ]
                      ],
                    ),
                  ),

                const SizedBox(height: 8),

                // Description section
                Container(
                  padding: const EdgeInsets.all(20),
                  color: Colors.white,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Mô tả sản phẩm', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
                      const SizedBox(height: 12),
                      Text(description, style: const TextStyle(fontSize: 14, color: Color(0xFF475569), height: 1.6)),
                    ],
                  ),
                ),
                
                const SizedBox(height: 8),

                // Comments section
                Container(
                  padding: const EdgeInsets.all(20),
                  color: Colors.white,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Đánh giá sản phẩm (${comments.length})', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
                      const SizedBox(height: 16),
                      if (isLoadingComments)
                        const Center(child: CircularProgressIndicator())
                      else if (comments.isEmpty)
                        const Text('Chưa có đánh giá nào.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic))
                      else
                        ...comments.map((cmt) {
                          final user = cmt['user'] != null ? cmt['user']['full_name'] : 'Người dùng';
                          final rating = cmt['rating'] ?? 5;
                          final content = cmt['comment'] ?? '';
                          final date = cmt['created_at']?.split('T')?[0] ?? '';
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                CircleAvatar(radius: 16, backgroundColor: Colors.grey.shade300, child: const Icon(Icons.person, size: 16, color: Colors.white)),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(user, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                          Text(date, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Row(children: List.generate(5, (index) => Icon(Icons.star, size: 12, color: index < rating ? Colors.amber : Colors.grey.shade300))),
                                      const SizedBox(height: 4),
                                      Text(content, style: const TextStyle(fontSize: 13, color: Color(0xFF334155))),
                                    ],
                                  )
                                )
                              ],
                            ),
                          );
                        }).toList(),
                    ],
                  ),
                ),

                const SizedBox(height: 20),
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
                  children: [
                    Expanded(
                      flex: 1,
                      child: OutlinedButton(
                        onPressed: () => _handleActionSelected('Thêm vào giỏ'),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          side: const BorderSide(color: Color(0xFF0EA5E9), width: 1.5),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30))
                        ),
                        child: const Text('Thêm vào giỏ', style: TextStyle(color: Color(0xFF0EA5E9), fontWeight: FontWeight.bold)),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 1,
                      child: ElevatedButton(
                        onPressed: () => _handleActionSelected('Mua ngay'),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: const Color(0xFF0EA5E9),
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30))
                        ),
                        child: const Text('Mua ngay', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                      ),
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

  Widget _buildFeatureItem(IconData icon, String title, String subtitle) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF64748B), size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(title, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                Text(subtitle, style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)), overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildColorChoice(String colorVal) {
    bool isHex = colorVal.startsWith('#');
    final isSelected = selectedColor == colorVal;

    // Map string hex to color if possible
    Color? clr;
    if (isHex) {
      try {
        clr = Color(int.parse(colorVal.replaceFirst('#', '0xFF')));
      } catch (_) {}
    } else {
      // Basic mapping for common Vietnamese colors
      switch(colorVal.toLowerCase().trim()) {
        case 'đỏ': case 'red': clr = Colors.red; break;
        case 'xanh': case 'xanh dương': case 'blue': clr = Colors.blue; break;
        case 'xanh lá': case 'green': clr = Colors.green; break;
        case 'vàng': case 'yellow': clr = Colors.yellow; break;
        case 'đen': case 'black': clr = Colors.black; break;
        case 'trắng': case 'white': clr = Colors.white; break;
        case 'hồng': case 'pink': clr = Colors.pink; break;
        case 'tím': case 'purple': clr = Colors.purple; break;
        case 'cam': case 'orange': clr = Colors.orange; break;
        case 'khaki': clr = Colors.brown.shade300; break;
        case 'xám': case 'gray': case 'grey': clr = Colors.grey; break;
      }
    }

    if (clr != null) {
      return GestureDetector(
        onTap: () => setState(() => selectedColor = colorVal),
        child: Container(
          margin: const EdgeInsets.only(right: 12),
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: isSelected ? const Color(0xFF0EA5E9) : Colors.transparent, width: 2),
          ),
          child: Container(
            width: 32, height: 32,
            decoration: BoxDecoration(
              color: clr,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.black12, width: 1)
            ),
          ),
        ),
      );
    } else {
      // Fallback to text badge (like size) if not mapeable
      return GestureDetector(
        onTap: () => setState(() => selectedColor = colorVal),
        child: Container(
          margin: const EdgeInsets.only(right: 12),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF0EA5E9).withOpacity(0.1) : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: isSelected ? const Color(0xFF0EA5E9) : const Color(0xFFE2E8F0)),
          ),
          child: Text(colorVal, style: TextStyle(
            color: isSelected ? const Color(0xFF0EA5E9) : const Color(0xFF475569), 
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
            fontSize: 13
          )),
        ),
      );
    }
  }

  Widget _buildSizeChoice(String sizeVal) {
    final isSelected = selectedSize == sizeVal;
    return GestureDetector(
      onTap: () => setState(() => selectedSize = sizeVal),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF0284C7) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isSelected ? const Color(0xFF0284C7) : const Color(0xFFE2E8F0)),
        ),
        child: Text(sizeVal, style: TextStyle(
          color: isSelected ? Colors.white : const Color(0xFF475569), 
          fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
          fontSize: 13
        )),
      ),
    );
  }

  Widget _imagePlaceholder() {
    return Container(width: double.infinity, height: 350, color: const Color(0xFFF1F5F9), child: const Center(child: Icon(Icons.image_not_supported, size: 60, color: Colors.grey)));
  }
}
