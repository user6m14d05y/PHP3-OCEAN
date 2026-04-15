import 'dart:convert';
import 'package:flutter/material.dart';
import 'productDetail.dart';
import 'screens/product_list_screen.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/notification_screen.dart';

const String kBaseUrl = 'http://localhost:8383/api';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  // ===== STATE =====
  List<dynamic> products = [];
  bool isLoading = true;
  String? errorMessage;
  int currentPage = 1;
  int totalPages = 1;
  int totalProducts = 0;
  String search = '';
  final ScrollController _scrollController = ScrollController();

  // ===== GỌI API =====
  Future<void> fetchProducts() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final url = '$kBaseUrl/products?page=$currentPage&search=$search';
      final response = await http.get(
        Uri.parse(url),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
          setState(() {
            products = fetched;
            totalPages = 1;
            totalProducts = fetched.length;
            isLoading = false;
          });
        } else if (data['data'] is List) {
          fetched = data['data'];
          setState(() {
            products = fetched;
            totalPages = data['total_pages'] ?? 1;
            totalProducts = data['total'] ?? fetched.length;
            isLoading = false;
          });
        }
      } else {
        setState(() {
          errorMessage = 'Lỗi server: ${response.statusCode}';
          isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        errorMessage = 'Không kết nối được API!\nLỗi: $e';
        isLoading = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    fetchProducts();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            currentPage = 1;
            await fetchProducts();
          },
          child: SingleChildScrollView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildHeader(),
                _buildSearchBar(),
                _buildHeroBanner(),
                _buildCategories(),
                _buildProductsSection(),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'Ocean Shop',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          Row(
            children: [
              IconButton(icon: const Icon(Icons.notifications_none, color: Color(0xFF64748B)), onPressed: () {
                Navigator.push(context, MaterialPageRoute(builder: (context) => const NotificationScreen()));
              }),
              IconButton(icon: const Icon(Icons.grid_view, color: Color(0xFF64748B)), onPressed: () {}),
            ],
          )
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(30),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, 4)),
          ],
        ),
        child: TextField(
          decoration: InputDecoration(
            hintText: 'Bạn muốn tìm gì?',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 15),
            prefixIcon: const Icon(Icons.search, color: Color(0xFF94A3B8)),
            suffixIcon: const Icon(Icons.filter_list, color: Color(0xFF0EA5E9)),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(vertical: 15),
          ),
          onChanged: (text) {
            // debounce 400ms
            Future.delayed(const Duration(milliseconds: 400), () {
              if (mounted) {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => ProductListScreen(searchQuery: text.trim()),
                  ),
                );
              }
            });
          },
          onSubmitted: (text) {
            if (text.trim().isNotEmpty) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ProductListScreen(searchQuery: text.trim()),
                ),
              );
            }
          },
        ),
      ),
    );
  }

  Widget _buildHeroBanner() {
    return Container(
      margin: const EdgeInsets.all(20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0284C7), Color(0xFF38BDF8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Text('LIMITED EDITION', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(height: 12),
          const Text(
            'Mùa Hè Rực Rỡ\nCÙNG OCEAN PACK',
            style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, height: 1.2),
          ),
          const SizedBox(height: 8),
          const Text(
            'Giảm ngay 25% cho tất cả thiết bị lặn chuyên nghiệp.',
            style: TextStyle(color: Colors.white70, fontSize: 13),
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () {},
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: const Color(0xFF0369A1),
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            ),
            child: const Text('Khám phá ngay', style: TextStyle(fontWeight: FontWeight.bold)),
          )
        ],
      ),
    );
  }

  Widget _buildCategories() {
    final categories = [
      {'icon': Icons.scuba_diving, 'name': 'Đồ lặn'},
      {'icon': Icons.surfing, 'name': 'Lướt ván'},
      {'icon': Icons.hiking, 'name': 'Dã ngoại'},
      {'icon': Icons.watch, 'name': 'Phụ kiện'},
    ];

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Danh mục phổ biến', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
              TextButton(onPressed: () {}, child: const Text('Xem tất cả', style: TextStyle(color: Color(0xFF0EA5E9), fontWeight: FontWeight.w600))),
            ],
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 100,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 10),
            itemCount: categories.length,
            itemBuilder: (context, index) {
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Column(
                  children: [
                    Container(
                      width: 60,
                      height: 60,
                      decoration: BoxDecoration(
                        color: const Color(0xFFE0F2FE),
                        borderRadius: BorderRadius.circular(30),
                      ),
                      child: Icon(categories[index]['icon'] as IconData, color: const Color(0xFF0284C7), size: 28),
                    ),
                    const SizedBox(height: 8),
                    Text(categories[index]['name'] as String, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF475569))),
                  ],
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildProductsSection() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
               const Text('Dành cho bạn', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
               const Icon(Icons.more_horiz, color: Color(0xFF94A3B8))
            ],
          ),
          const SizedBox(height: 16),
          if (isLoading && products.isEmpty)
            const Center(child: CircularProgressIndicator(color: Color(0xFF0EA5E9)))
          else if (errorMessage != null)
            Center(child: Text(errorMessage!, style: const TextStyle(color: Colors.red)))
          else if (products.isEmpty)
            const Center(child: Text('Không có sản phẩm nào phù hợp'))
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                childAspectRatio: 0.65,
              ),
              itemCount: products.length,
              itemBuilder: (context, index) {
                return _buildProductCard(products[index]);
              },
            ),
        ],
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice = product['min_price'] ?? (product['lowest_price_variant'] != null ? product['lowest_price_variant']['price'] : 0);
    
    String imageUrl = '';
    final rawImage = product['thumbnail_url'] ?? '';
    if (rawImage.toString().isNotEmpty) {
      if (rawImage.toString().startsWith('http')) {
        imageUrl = rawImage.toString();
      } else {
        imageUrl = 'http://localhost:8383/api/image-proxy?path=$rawImage';
      }
    }

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ProductDetailScreen(product: product),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10, offset: const Offset(0, 4)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
                  child: imageUrl.isNotEmpty
                      ? Image.network(imageUrl, height: 160, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _imagePlaceholder())
                      : _imagePlaceholder(),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: GestureDetector(
                    onTap: () async {
                      try {
                        final prefs = await SharedPreferences.getInstance();
                        final token = prefs.getString('access_token');
                        if (token == null) {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng đăng nhập để lưu!')));
                          return;
                        }
                        await http.post(
                          Uri.parse('$kBaseUrl/profile/favorites/toggle'),
                          headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
                          body: jsonEncode({'product_id': product['product_id'] ?? product['id']})
                        );
                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đã cập nhật danh sách yêu thích!'), duration: Duration(seconds: 1)));
                      } catch (_) {}
                    },
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                      child: const Icon(Icons.favorite_border, size: 16, color: Color(0xFF94A3B8)),
                    ),
                  ),
                )
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name.toString(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF1E293B), height: 1.3),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _formatPrice(rawPrice),
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFF0284C7)),
                      ),
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(color: const Color(0xFF0284C7), borderRadius: BorderRadius.circular(8)),
                        child: const Icon(Icons.shopping_cart_outlined, size: 14, color: Colors.white),
                      )
                    ],
                  )
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _imagePlaceholder() {
    return Container(height: 160, color: const Color(0xFFF1F5F9), child: const Center(child: Icon(Icons.image_not_supported, size: 30, color: Colors.grey)));
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

}
