import 'dart:convert';
import 'package:flutter/material.dart';
import 'productDetail.dart';
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
          // API trả thẳng mảng (không phân trang)
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

        // Cuộn về đầu mỗi khi đổi trang
        if (_scrollController.hasClients) {
          _scrollController.animateTo(
            0,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      } else {
        setState(() {
          errorMessage = 'Lỗi server: ${response.statusCode}';
          isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        errorMessage =
            'Không kết nối được API!\n\nKiểm tra:\n1. kBaseUrl có đúng chưa?\n2. Server có đang chạy không?\n\nLỗi: $e';
        isLoading = false;
      });
    }
  }

  void _goToPage(int page) {
    if (page < 1 || page > totalPages || page == currentPage) return;
    setState(() => currentPage = page);
    fetchProducts();
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

  // ===== BUILD UI =====
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ocean Shop'),
        backgroundColor: const Color(0xFF0EA5E9),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() => currentPage = 1);
              fetchProducts();
            },
            tooltip: 'Tải lại',
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    return Column(
      children: [
        // 1. THANH TÌM KIẾM 
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 5),
          child: TextField(
            decoration: InputDecoration(
              hintText: 'Bạn muốn tìm gì?',
              suffixIcon: const IconButton(onPressed: null, icon: Icon(Icons.search, color: Color(0xFF0EA5E9))),
              suffixIconConstraints: const BoxConstraints(minWidth: 40, minHeight: 0),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(20.0),
              ),
            ),
            onChanged: (text) {
              search = text;
              currentPage = 1; // Reset về trang 1 khi tìm kiếm
              fetchProducts();
            },
          ),
        ),

        // 2. NỘI DUNG CHÍNH
        Expanded(
          child: Builder(builder: (context) {
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

            if (errorMessage != null) {
              return Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.wifi_off, size: 64, color: Colors.grey),
                    const SizedBox(height: 16),
                    Text(errorMessage!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      onPressed: fetchProducts,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Thử lại'),
                    ),
                  ],
                ),
              );
            }

            // Không tìm thấy sản phẩm
            if (products.isEmpty) {
              return const Center(child: Text('Không có sản phẩm nào phù hợp'));
            }
            return Column(
              children: [
                // Thanh thông tin: tổng sản phẩm + trang hiện tại
                Container(
                  // horizontal la 2 ben trai pha, va vertical la tren duoi
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                  margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  // Color neu su dung color thi khong su dung decoration
                  decoration: BoxDecoration(
                    color: const Color(0xFFE0F2FE), // ← chuyển color vào đây
                    borderRadius: BorderRadius.circular(20.0),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.inventory_2, size: 16, color: Color(0xFF0EA5E9)),
                          const SizedBox(width: 6),
                          Text(
                            '$totalProducts sản phẩm',
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF0369A1),
                            ),
                          ),
                        ],
                      ),
                      Text(
                        'Trang $currentPage / $totalPages',
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF0369A1),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),

                // Lưới GridView
                Expanded(
                  child: GridView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(12),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 10,
                      mainAxisSpacing: 10,
                      childAspectRatio: 0.65,
                    ),
                    itemCount: products.length,
                    itemBuilder: (context, index) {
                      return _buildProductCard(products[index]);
                    },
                  ),
                ),

                // Thanh phân trang số
                if (totalPages > 1) _buildPagination(),
              ],
            );
          }),
        ),
      ],
    );
  }

  // ===== THANH PHÂN TRANG SỐ =====
  Widget _buildPagination() {
    // Tạo danh sách các số trang cần hiển thị (window ±2 quanh trang hiện tại)
    List<int?> pageItems = _buildPageItems();

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE0F2FE), width: 1.5)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Nút Previous
          _PageBtn(
            icon: Icons.chevron_left,
            onTap: currentPage > 1 ? () => _goToPage(currentPage - 1) : null,
          ),
          const SizedBox(width: 4),

          // Các số trang
          ...pageItems.map((p) {
            if (p == null) {
              // Dấu "..."
              return const Padding(
                padding: EdgeInsets.symmetric(horizontal: 4),
                child: Text('…', style: TextStyle(color: Colors.grey)),
              );
            }
            final isActive = p == currentPage;
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: InkWell(
                onTap: () => _goToPage(p),
                borderRadius: BorderRadius.circular(8),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 36,
                  height: 36,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: isActive ? const Color(0xFF0EA5E9) : Colors.transparent,
                    borderRadius: BorderRadius.circular(8),
                    border: isActive
                        ? null
                        : Border.all(color: const Color(0xFFCBD5E1)),
                  ),
                  child: Text(
                    '$p',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: isActive ? Colors.white : const Color(0xFF334155),
                    ),
                  ),
                ),
              ),
            );
          }),

          const SizedBox(width: 4),
          // Nút Next
          _PageBtn(
            icon: Icons.chevron_right,
            onTap: currentPage < totalPages ? () => _goToPage(currentPage + 1) : null,
          ),
        ],
      ),
    );
  }

  /// Tạo danh sách trang hiển thị, null = dấu "..."
  List<int?> _buildPageItems() {
    if (totalPages <= 7) {
      return List.generate(totalPages, (i) => i + 1);
    }

    final List<int?> items = [];
    // Luôn hiện trang 1
    items.add(1);

    if (currentPage > 3) items.add(null); // "..."

    for (int p = currentPage - 1; p <= currentPage + 1; p++) {
      if (p > 1 && p < totalPages) items.add(p);
    }

    if (currentPage < totalPages - 2) items.add(null); // "..."

    // Luôn hiện trang cuối
    items.add(totalPages);

    return items;
  }

  // ===== CARD SẢN PHẨM =====
  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice = product['min_price'] ??
        (product['lowest_price_variant'] != null
            ? product['lowest_price_variant']['price']
            : 0);

    String imageUrl = '';
    final rawImage = product['thumbnail_url'] ?? '';
    if (rawImage.toString().isNotEmpty) {
      if (rawImage.toString().startsWith('http')) {
        imageUrl = rawImage.toString();
      } else {
        imageUrl = 'http://localhost:8383/storage/$rawImage';
      }
    }

    final rating = product['average_rating'] ?? 0;

    return Card(
      elevation: 4,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => ProductDetailScreen(product: product),
            ),
          );
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Ảnh sản phẩm
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

// ===== NÚT PREV / NEXT =====
class _PageBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _PageBtn({required this.icon, this.onTap});

  @override
  Widget build(BuildContext context) {
    final enabled = onTap != null;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: enabled ? const Color(0xFF0EA5E9) : const Color(0xFFCBD5E1),
          ),
        ),
        child: Icon(
          icon,
          size: 20,
          color: enabled ? const Color(0xFF0EA5E9) : Colors.grey,
        ),
      ),
    );
  }
}
