class Category {
  final int categoryId;
  final String name;

  Category({
    required this.categoryId,
    required this.name,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      categoryId: json['category_id'] ?? 0,
      name: json['name'] ?? '',
    );
  }
}

class Product {
  final int productId;
  final String name;
  final num minPrice; // dùng num thay cho double/int vì JSON giá có thể tùy biến
  final String thumbnailUrl;
  final Category? category;

  Product({
    required this.productId,
    required this.name,
    required this.minPrice,
    required this.thumbnailUrl,
    this.category,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      productId: json['product_id'] ?? 0,
      name: json['name'] ?? '',
      minPrice: json['min_price'] ?? 0,
      thumbnailUrl: json['thumbnail_url'] ?? '',
      category: json['category'] != null ? Category.fromJson(json['category']) : null,
    );
  }
}
