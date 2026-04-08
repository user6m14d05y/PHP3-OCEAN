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
  int CurrentPage = 1 ;
  bool isFetchingMore = false;
  bool hasMore = true;
  String search = '';
  final ScrollController _scrollController = ScrollController();

  // ===== GỌI API =====
  Future<void> fetchProducts({bool isLoadMore = false}) async {
    if (isLoadMore) {
      setState(() => isFetchingMore = true);
    } else {
      setState(() {
        isLoading = true;
        errorMessage = null;
        hasMore = true;
        products.clear();
      });
    }

    try {
      final url = '$kBaseUrl/products?page=$CurrentPage&search=$search';
      // Gọi API: GET /api/products
      final response = await http.get(
        Uri.parse(url),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        List<dynamic> fetchedProducts = [];
        if(data is List){
          fetchedProducts = data;
        hasMore = false;
        }else if(data['data'] is List){ 
          fetchedProducts = data['data'];
          int lastPage = data['last_page'] ?? 1;
          if(CurrentPage >= lastPage){
            hasMore = false;
          }
        }
        // API trả về dạng { data: [...] } hoặc [{...}, {...}]
        setState(() {
          if(isLoadMore){
            products.addAll(fetchedProducts);
            isFetchingMore = false;
          }else {
            products = fetchedProducts;
            isLoading = false;
          }
        });
      } else {
        setState(() {
          errorMessage = 'Lỗi server: ${response.statusCode}';
          isLoading = false;
          isFetchingMore = false;
        });
      }
    } catch (e) {
      setState(() {
        errorMessage = 'Không kết nối được API!\n\nKiểm tra:\n1. kBaseUrl có đúng chưa?\n2. Server có đang chạy không?\n\nLỗi: $e';
        isLoading = false;
        isFetchingMore = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    fetchProducts(); // Gọi API ngay khi mở màn hình

    _scrollController.addListener(() {
      if(_scrollController.position.pixels >= _scrollController.position.maxScrollExtent){
        if(!isLoading && !isFetchingMore && hasMore){
          CurrentPage++;
          fetchProducts(isLoadMore: true);
        }
      }
    });
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
            onPressed: () => fetchProducts(), 
            tooltip: 'Tải lại',
          ),
        ],
      ),

      // === BODY ===
      body: _buildBody(),
    );
  }

    Widget _buildBody() {
    return Column(
      children: [
        // 1. THANH TÌM KIẾM (LUÔN LUÔN HIỆN MẶC KỆ Ở DƯỚI CÓ LOADING)
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: TextField(
            decoration: InputDecoration(
              hintText: 'Bạn muốn tìm gì?',
              prefixIcon: const Icon(Icons.search, color: Color(0xFF0EA5E9)),
              contentPadding: const EdgeInsets.all(0),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(30.0),
              ),
            ),
            onChanged: (text) {
              search = text; // Lưu chữ
              fetchProducts(isLoadMore: false); // Chạy API lấy trang 1
            },
          ),
        ),

        // 2. PHẦN KHU VỰC HIỂN THỊ CHÍNH (Được bảo vệ bằng Expanded)
        Expanded(
          child: Builder(builder: (context) {
            // Đang tải mới -> Hiện vòng xoay
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

            // Có lỗi -> Hiện thông báo Wifi Off
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
                      onPressed: () => fetchProducts(),
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

            // Trả về đúng cái Cột Thống Kê + Lưới GridView
            return Column(
              children: [
                // Thống kê đếm số
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  color: const Color(0xFFE0F2FE),
                  child: Row(
                    children: [
                      const Icon(Icons.inventory_2, size: 16, color: Color(0xFF0EA5E9)),
                      const SizedBox(width: 6),
                      Text(
                        'Tìm thấy ${products.length} sản phẩm',
                        style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF0369A1)),
                      ),
                    ],
                  ),
                ),
                
                // Mạng lưới GridView
                Expanded(
                  child: GridView.builder(
                    controller: _scrollController, // Quan trọng: Có để vuốt trang
                    padding: const EdgeInsets.all(20),
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

                // Vòng xoay mini ở đáy khi load thêm trang 2, 3
                if (isFetchingMore)
                  const Padding(
                    padding: EdgeInsets.all(8.0),
                    child: CircularProgressIndicator(color: Color(0xFF0EA5E9)),
                  ),
              ],
            );
          }),
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
      elevation: 5,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(2)),
      // Click vao cat
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
