import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/product_model.dart';

class ApiService {
  // Thay đổi 127.0.0.1 thành localhost nếu chạy trực tiếp trên desktop app (Windows/Mac)
  // Trong Android Emulator, 127.0.0.1 trỏ về localhost của máy tính chứa server
  static const String baseUrl = 'http://127.0.0.1:8383/api';

  static Future<List<Product>> fetchProducts() async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/products'));

      if (response.statusCode == 200) {
        final Map<String, dynamic> jsonResponse = json.decode(response.body);
        
        // Trích xuất mảng data theo cấu trúc phổ biến của Laravel pagination/resource
        List<dynamic> data = [];
        if (jsonResponse['data'] != null) {
          data = jsonResponse['data'];
        } else if (jsonResponse.containsKey('products') && jsonResponse['products']['data'] != null) {
          data = jsonResponse['products']['data'];
        }

        return data.map((json) => Product.fromJson(json)).toList();
      } else {
        throw Exception('Failed to fetch data (Status ${response.statusCode})');
      }
    } catch (e) {
      throw Exception('API Connection Error: $e');
    }
  }
}
