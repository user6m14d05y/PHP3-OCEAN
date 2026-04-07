import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

// ========== BƯỚC 1: ĐỔI IP theo môi trường đang test ==========
// - Chạy Windows Desktop (hiện tại):  'http://localhost:8383/api'
// - Dùng Android Emulator (máy ảo):   'http://10.0.2.2:8383/api'
// - Dùng điện thoại thật (cùng wifi): 'http://192.168.x.x:8383/api'
// - Dùng server aaPanel:              'http://14.191.126.22/api'
const String kBaseUrl = 'http://localhost:8383/api';
// =============================================================================

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  // ===== STATE =====
  List<dynamic> products = [];    // danh sách sản phẩm
  bool isLoading = true;          // đang tải hay không
  String? errorMessage;           // thông báo lỗi nếu có

  // ===== GỌI API =====
  Future<void> fetchProducts() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      // Gọi API: GET /api/products
      final response = await http.get(
        Uri.parse('$kBaseUrl/products'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);

        // API trả về dạng { data: [...] } hoặc [{...}, {...}]
        setState(() {
          if (data is List) {
            products = data;
          } else if (data['data'] is List) {
            products = data['data'];
          } else {
            products = [];
          }
          isLoading = false;
        });
      } else {
        setState(() {
          errorMessage = 'Lỗi server: ${response.statusCode}';
          isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        errorMessage = 'Không kết nối được API!\n\nKiểm tra:\n1. kBaseUrl có đúng chưa?\n2. Server có đang chạy không?\n\nLỗi: $e';
        isLoading = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    fetchProducts(); // Gọi API ngay khi mở màn hình
  }

  // ===== BUILD UI =====
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // === APPBAR ===
      appBar: AppBar(
        title: const Text('Ocean Shop'),
        backgroundColor: const Color(0xFF0EA5E9),
        foregroundColor: Colors.white,
        actions: [
          // Nút reload
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: fetchProducts,
            tooltip: 'Tải lại',
          ),
        ],
      ),

      // === BODY ===
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    // Đang tải → hiện vòng xoay
    if (isLoading) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: Color(0xFF0EA5E9)),
            SizedBox(height: 16),
            Text('Đang tải sản phẩm...'),
          ],
        ),
      );
    }

    // Có lỗi → hiện thông báo + nút thử lại
    if (errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.wifi_off, size: 64, color: Colors.grey),
              const SizedBox(height: 16),
              Text(
                errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red),
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: fetchProducts,
                icon: const Icon(Icons.refresh),
                label: const Text('Thử lại'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0EA5E9),
                  foregroundColor: Colors.white,
                ),
              ),
            ],
          ),
        ),
      );
    }

    // Không có sản phẩm
    if (products.isEmpty) {
      return const Center(child: Text('Không có sản phẩm nào'));
    }

    // Có data → hiện danh sách
    return Column(
      children: [
        // Thống kê nhỏ
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          color: const Color(0xFFE0F2FE),
          child: Row(
            children: [
              const Icon(Icons.inventory_2, size: 16, color: Color(0xFF0EA5E9)),
              const SizedBox(width: 6),
              Text(
                'Tổng ${products.length} sản phẩm',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0369A1),
                ),
              ),
            ],
          ),
        ),

        // Danh sách sản phẩm dạng lưới
        Expanded(
          child: GridView.builder(
            padding: const EdgeInsets.all(20),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,        // 2 cột
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: 0.72,   // tỷ lệ chiều cao/rộng
            ),
            itemCount: products.length,
            itemBuilder: (context, index) {
              final product = products[index];
              return _buildProductCard(product);
            },
          ),
        ),
      ],
    );
  }

  // ===== CARD SẢN PHẨM =====
  Widget _buildProductCard(Map<String, dynamic> product) {
    // 1. Lấy tên sản phẩm
    final name = product['name'] ?? 'Không tên';

    // 2. Lấy giá (Trong Laravel của bạn là min_price hoặc lấy từ variant)
    final dynamic rawPrice = product['min_price'] ?? 
                             (product['lowest_price_variant'] != null ? product['lowest_price_variant']['price'] : 0);
    
    // 3. Xử lý ảnh (Nối thêm đường dẫn storage của Laravel)
    String imageUrl = '';
    final rawImage = product['thumbnail_url'] ?? '';
    if (rawImage.toString().isNotEmpty) {
      if (rawImage.toString().startsWith('http')) {
        imageUrl = rawImage.toString();
      } else {
        // Nối IP server + /storage/ + đường dẫn ảnh
        imageUrl = 'http://localhost:8383/storage/$rawImage';
      }
    }

    final rating = product['average_rating'] ?? 0;

    return Card(
      elevation: 2,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Hình ảnh sản phẩm
          Expanded(
            child: imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    // Nếu lỗi ảnh (do sai link hoặc ảnh ko tồn tại) thì hiện icon thay thế
                    errorBuilder: (_, __, ___) => _imagePlaceholder(),
                  )
                : _imagePlaceholder(),
          ),

          // Thông tin sản phẩm
          Padding(
            padding: const EdgeInsets.all(8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name.toString(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                // Hiển thị giá đã được format
                Text(
                  _formatPrice(rawPrice),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF0EA5E9),
                  ),
                ),
                const SizedBox(height: 2),
                if (rating != 0)
                  Row(
                    children: [
                      const Icon(Icons.star, size: 12, color: Colors.amber),
                      const SizedBox(width: 2),
                      Text(
                        rating.toString(),
                        style: const TextStyle(fontSize: 11, color: Colors.grey),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _imagePlaceholder() {
    return Container(
      color: const Color(0xFFE0F2FE),
      child: const Center(
        child: Icon(Icons.image_not_supported, size: 40, color: Colors.grey),
      ),
    );
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
}
