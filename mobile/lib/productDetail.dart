import 'package:flutter/material.dart';

class ProductDetailScreen extends StatelessWidget {
  final Map<String, dynamic> product;

  const ProductDetailScreen({super.key, required this.product});

  @override
  Widget build(BuildContext context) {
    // Tinh gia
    final dynamic price = product['min_price'] ?? (product['lowest_price_variant'] != null ? product['lowest_price_variant']['price'] : 0);
    // Noi image
    String imageUrl = '';
    final RawImage = product['thumbnail_url'] ?? '';
    if(RawImage.startsWith('http')){
      imageUrl = RawImage;
    }else{
      imageUrl = 'http://localhost:8383/storage/' + RawImage;
    }
    // Remove the p
    String removeP = product['description'] ?? 'Không có mô tả nào ở đây';
    removeP = removeP.replaceAll('<p>', '').replaceAll('</p>', '');
    removeP = removeP.replaceAll('<br>', '');
    removeP = removeP.replaceAll('</br>', '');
    removeP = removeP.replaceAll('<strong>', '').replaceAll('</strong>', '');
    removeP = removeP.replaceAll('<b>', '').replaceAll('</b>', '');
    removeP = removeP.replaceAll('<i>', '').replaceAll('</i>', '');
    removeP = removeP.replaceAll('<u>', '').replaceAll('</u>', '');
    removeP = removeP.replaceAll('<em>', '').replaceAll('</em>', '');
    removeP = removeP.replaceAll('<strong>', '').replaceAll('</strong>', '');
    removeP = removeP.replaceAll('<strong>', '').replaceAll('</strong>', '');
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
            // Hình ảnh
            if (imageUrl.isNotEmpty)
              Image.network(
                imageUrl,
                width: double.infinity,
                height: 300,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) {
                  return Container(
                    height: 300,
                    color: Colors.grey[200],
                    child: const Icon(Icons.broken_image, size: 80, color: Colors.grey),
                  );
                },
              ),

            // Thông tin chi tiết
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Tên sản phẩm
                  Text(
                    product['name'] ?? 'Không tên',
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF0EA5E9),
                    ),
                  ),
                  const SizedBox(height: 8),

                  // Giá
                  Row(
                    children: [
                      const Icon(Icons.attach_money, color: Colors.green, size: 24),
                      const SizedBox(width: 4),
                      Text(
                        '${price} VNĐ',
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: Colors.green,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // Mô tả
                  const Text(
                    'Mô tả sản phẩm',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    removeP,
                    style: const TextStyle(fontSize: 16, color: Colors.black87),
                  ),
                  const SizedBox(height: 16),

                  // Thông tin khác
                  const Divider(),
                  const SizedBox(height: 8),
                  Text('SKU: ${product['sku'] ?? 'N/A'}'),
                  Text('Danh mục: ${product['category'] ?? 'N/A'}'),
                  Text('Thương hiệu: ${product['brand'] ?? 'N/A'}'),
                  Text('Còn hàng: ${product['stock'] ?? 0}'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
