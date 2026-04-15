import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class AuthService {
  static const String kBaseUrl = 'http://localhost:8383/api';
  static const String keyToken = 'access_token';
  static const String keyUser = 'user_data';

  // ========== LOGIN ==========
  static Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$kBaseUrl/login'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      ).timeout(const Duration(seconds: 15));

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 && data['status'] == 'success') {
        // Save Token
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(keyToken, data['access_token']);
        // Save User info
        await prefs.setString(keyUser, jsonEncode(data['user']));
        return {'success': true, 'message': 'Đăng nhập thành công'};
      } else {
        return {'success': false, 'message': data['message'] ?? 'Lỗi đăng nhập. Vui lòng thử lại.'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ.'};
    }
  }

  // ========== REGISTER ==========
  static Future<Map<String, dynamic>> register(String name, String email, String password, String passwordConfirm) async {
    try {
      final response = await http.post(
        Uri.parse('$kBaseUrl/register'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'full_name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirm,
        }),
      ).timeout(const Duration(seconds: 15));

      final data = jsonDecode(response.body);

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (data['status'] == 'success') {
          // If register returns token, we could optionally save it, but here we just return success
          return {'success': true, 'message': 'Đăng ký thành công'};
        } else {
          return {'success': false, 'message': data['message'] ?? 'Lỗi đăng ký.'};
        }
      } else {
        // Handle Validation Errors
        if (data['errors'] != null) {
          final errors = data['errors'] as Map<String, dynamic>;
          final firstError = errors.values.first[0];
          return {'success': false, 'message': firstError};
        }
        return {'success': false, 'message': data['message'] ?? 'Lỗi đăng ký. Vui lòng thử lại.'};
      }
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ.'};
    }
  }

  // ========== LOGOUT ==========
  static Future<bool> logout() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(keyToken);
      if (token != null) {
        await http.post(
          Uri.parse('$kBaseUrl/logout'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
          },
        );
      }
      await prefs.remove(keyToken);
      await prefs.remove(keyUser);
      return true;
    } catch (e) {
      // Even if API fails, we wipe local storage
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(keyToken);
      await prefs.remove(keyUser);
      return true;
    }
  }

  // ========== CHECK STATUS ==========
  static Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(keyToken);
    return token != null && token.isNotEmpty;
  }
}
