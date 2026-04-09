import 'package:flutter/material.dart';

class ProductDetailScreen extends StatelessWidget {
  final Map<String, dynamic> product;

  const ProductDetailScreen({super.key, required this.product});

  // Format price
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

  // lay tong stock uu tien variants_sum_stock fallback lowest_price_variant.stock
  int _getStock() {
    // API tra ve variants_sum_stock (tong kho tat ca variant)
    final sumStock = product['variants_sum_stock'];
    if (sumStock != null) {
      return int.tryParse(sumStock.toString()) ?? 0;
    }
    // lay stock tu variant re nhat
    final lowestVariant = product['lowest_price_variant'];
    if (lowestVariant is Map) {
      return int.tryParse(lowestVariant['stock']?.toString() ?? '0') ?? 0;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    // Tinh price
    final dynamic priceRaw = product['min_price'] ??
        (product['lowest_price_variant'] is Map
            ? product['lowest_price_variant']['price']
            : 0);

    // Noi image url
    String imageUrl = '';
    final rawImage = (product['thumbnail_url'] ?? '').toString();
    if (rawImage.isNotEmpty) {
      if (rawImage.startsWith('http')) {
        imageUrl = rawImage;
      } else {
        imageUrl = 'http://localhost:8383/storage/$rawImage';
      }
    }

    // format sach description html
    String description = product['description'] ?? '';
    if (description.isEmpty) {
      description = 'Không có mô tả nào ở đây';
    } else {
      description = description
          .replaceAll(RegExp(r'<[^>]*>'), '') // Remove moi the html
          .replaceAll('&nbsp;', ' ')
          .replaceAll('&amp;', '&')
          .trim();
      if (description.isEmpty) description = 'Không có mô tả nào ở đây';
    }

    // Category & Brand
    final categoryName = product['category'] is Map
        ? (product['category']['name'] ?? 'N/A')
        : 'N/A';
    final brandName = product['brand'] is Map
        ? (product['brand']['name'] ?? 'N/A')
        : 'N/A';

    // Stock
    final stock = _getStock();
    final inStock = stock > 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(product['name'] ?? 'Chi tiết sản phẩm'),
        backgroundColor: const Color(0xFF0EA5E9),
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // image
            imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    width: double.infinity,
                    height: 300,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) {
                      return _imagePlaceholder();
                    },
                  )
                : _imagePlaceholder(),

            // product info
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // name product
                  Text(
                    product['name'] ?? 'Không tên',
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF0EA5E9),
                    ),
                  ),
                  const SizedBox(height: 8),

                  // format price
                  Row(
                    children: [
                      const SizedBox(width: 6),
                      Text(
                        _formatPrice(priceRaw),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: Colors.blue,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Badge stock
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: inStock
                          ? Colors.blue.withOpacity(0.1)
                          : Colors.red.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: inStock ? Colors.blue : Colors.red,
                        width: 1,
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const SizedBox(width: 4),
                        Text(
                          inStock ? 'Còn hàng $stock' : 'Hết hàng',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: inStock ? Colors.blue : Colors.red,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Mô tả
                  const Text(
                    'Mô tả sản phẩm',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    description,
                    style: const TextStyle(
                        fontSize: 14,
                        color: Colors.black87,
                        height: 1.5),
                  ),
                  const SizedBox(height: 16),

                  // info product
                  const Divider(),
                  const SizedBox(height: 8),
                  _infoRow('Danh mục', categoryName),
                  const SizedBox(height: 6),
                  _infoRow('Thương hiệu', brandName),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Placeholder image
  Widget _imagePlaceholder() {
    return Container(
      width: double.infinity,
      height: 300,
      color: Colors.grey[200],
      child: const Icon(Icons.image_not_supported,
          size: 80, color: Colors.grey),
    );
  }


  Widget _infoRow(String label, String value) {
    return Row(
      children: [
        Text(
          '$label: ',
          style: const TextStyle(
              fontWeight: FontWeight.w600, fontSize: 13),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 13, color: Colors.black87),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
