import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../productDetail.dart';

const String kBaseUrl = 'http://localhost:8383/api';

class FavoriteScreen extends StatefulWidget {
  const FavoriteScreen({super.key});

  @override
  State<FavoriteScreen> createState() => _FavoriteScreenState();
}

class _FavoriteScreenState extends State<FavoriteScreen> {
  List<dynamic> favorites = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    fetchFavorites();
  }

  Future<void> fetchFavorites() async {
    setState(() => isLoading = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      if (token == null) {
        setState(() => isLoading = false);
        return;
      }

      final res = await http.get(
        Uri.parse('$kBaseUrl/profile/favorites'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            favorites = data['data'] ?? [];
            isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  Future<void> toggleFavorite(int productId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      await http.post(
        Uri.parse('$kBaseUrl/profile/favorites/toggle'),
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
        body: jsonEncode({'product_id': productId})
      );
      fetchFavorites();
    } catch (e) {
      // bỏ qua
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
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Sản phẩm yêu thích', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0EA5E9)),
      ),
      body: isLoading
        ? const Center(child: CircularProgressIndicator(color: Color(0xFF0EA5E9)))
        : favorites.isEmpty
          ? const Center(child: Text('Chưa có sản phẩm yêu thích nào.', style: TextStyle(color: Colors.grey)))
          : GridView.builder(
              padding: const EdgeInsets.all(16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                childAspectRatio: 0.65,
              ),
              itemCount: favorites.length,
              itemBuilder: (context, index) {
                final fav = favorites[index];
                final product = fav['product'];
                if (product == null) return const SizedBox.shrink();

                final name = product['name'] ?? 'Không tên';
                final productId = product['product_id'] ?? fav['product_id'];

                // Lấy giá từ lowestPriceVariant hoặc min_price
                dynamic price = 0;
                if (product['lowest_price_variant'] != null) {
                  price = product['lowest_price_variant']['price'] ?? 0;
                } else if (product['min_price'] != null) {
                  price = product['min_price'];
                }

                // Lấy ảnh từ mainImage hoặc thumbnail_url
                String imageUrl = '';
                if (product['main_image'] != null) {
                  final rawImage = product['main_image']['image_url'] ?? '';
                  if (rawImage.isNotEmpty) {
                    imageUrl = rawImage.startsWith('http') ? rawImage : 'http://localhost:8383/api/image-proxy?path=$rawImage';
                  }
                } else {
                  final rawImage = product['thumbnail_url'] ?? '';
                  if (rawImage.toString().isNotEmpty) {
                    imageUrl = rawImage.toString().startsWith('http') ? rawImage.toString() : 'http://localhost:8383/api/image-proxy?path=$rawImage';
                  }
                }

                return GestureDetector(
                  onTap: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => ProductDetailScreen(product: product)));
                  },
                  child: Container(
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            ClipRRect(
                              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                              child: imageUrl.isNotEmpty
                                ? Image.network(imageUrl, height: 160, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_,__,___) => Container(height: 160, color: Colors.grey.shade200))
                                : Container(height: 160, color: Colors.grey.shade200),
                            ),
                            Positioned(
                              top: 8, right: 8,
                              child: GestureDetector(
                                onTap: () => toggleFavorite(productId),
                                child: Container(
                                  padding: const EdgeInsets.all(6),
                                  decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                                  child: const Icon(Icons.favorite, size: 16, color: Colors.red),
                                ),
                              )
                            )
                          ],
                        ),
                        Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                              const SizedBox(height: 8),
                              Text(_formatPrice(price), style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0284C7))),
                            ],
                          )
                        )
                      ],
                    ),
                  )
                );
              }
            )
    );
  }
}
