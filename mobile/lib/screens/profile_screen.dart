import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../services/auth_service.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'login_screen.dart';
import 'main_wrapper.dart';
import 'address_screen.dart';
import 'change_password_screen.dart';
import 'favorite_screen.dart';
import 'edit_profile_screen.dart';

const String kBaseUrl = 'http://localhost:8383/api';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? userData;
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    fetchProfile();
  }

  Future<void> fetchProfile() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      
      if (token == null) {
        if (mounted) {
          Navigator.pushReplacement(context, MaterialPageRoute(builder: (context) => const LoginScreen()));
        }
        return;
      }

      final response = await http.get(
        Uri.parse('$kBaseUrl/me'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      ).timeout(const Duration(seconds: 15));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (mounted) {
          setState(() {
            userData = data['user'];
            isLoading = false;
          });
        }
      } else if (response.statusCode == 401) {
        // Token hết hạn → xóa token và redirect đăng nhập
        await prefs.remove('access_token');
        if (mounted) {
          Navigator.pushReplacement(context, MaterialPageRoute(builder: (context) => const LoginScreen()));
        }
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Không thể lấy thông tin (${response.statusCode})';
            isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ.';
          isLoading = false;
        });
      }
    }
  }

  void _handleLogout() async {
    // Show loading
    showDialog(context: context, barrierDismissible: false, builder: (context) => const Center(child: CircularProgressIndicator()));
    
    await AuthService.logout();
    
    if (mounted) {
      Navigator.pop(context); // hide loading
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const MainWrapper()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0EA5E9),
        elevation: 0,
        title: const Text('Hồ Sơ Cá Nhân', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
        centerTitle: true,
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFF0EA5E9)));
    }
    
    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.person_off_outlined, size: 60, color: Colors.grey),
            const SizedBox(height: 16),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () {
                setState(() { isLoading = true; errorMessage = null; });
                fetchProfile();
              },
              child: const Text('Thử lại'),
            )
          ],
        ),
      );
    }

    final name = userData?['full_name'] ?? userData?['name'] ?? 'Khách hàng';
    final email = userData?['email'] ?? 'Không có email';
    final avatar = userData?['avatar_url'];

    return SingleChildScrollView(
      child: Column(
        children: [
          // Header Background
          Container(
            padding: const EdgeInsets.only(bottom: 30, top: 20, left: 24, right: 24),
            decoration: const BoxDecoration(
              color: Color(0xFF0EA5E9),
              borderRadius: BorderRadius.only(bottomLeft: Radius.circular(30), bottomRight: Radius.circular(30)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 36,
                  backgroundColor: Colors.white,
                  backgroundImage: avatar != null ? NetworkImage(avatar.startsWith('http') ? avatar : 'http://localhost:8383/api/image-proxy?path=$avatar') : null,
                  child: avatar == null ? const Icon(Icons.person, size: 40, color: Color(0xFF0EA5E9)) : null,
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 4),
                      Text(email, style: const TextStyle(color: Colors.white70, fontSize: 13)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          
          const SizedBox(height: 24),
          
          // Menu
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              children: [
                _buildMenuItem(Icons.edit_outlined, 'Chỉnh sửa hồ sơ', () async {
                  if (userData == null) return;
                  final updated = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => EditProfileScreen(userData: userData!)),
                  );
                  if (updated != null && mounted) {
                    setState(() => userData = updated);
                  }
                }),
                _buildMenuItem(Icons.location_on_outlined, 'Sổ địa chỉ', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const AddressScreen()));
                }),
                _buildMenuItem(Icons.lock_outline, 'Đổi mật khẩu', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const ChangePasswordScreen()));
                }),
                _buildMenuItem(Icons.favorite_border, 'Sản phẩm yêu thích', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const FavoriteScreen()));
                }),
                _buildMenuItem(Icons.article_outlined, 'Chính sách & Quy định', () {}),
                const SizedBox(height: 16),
                _buildMenuItem(Icons.logout, 'Đăng xuất', _handleLogout, isLogout: true),
              ],
            ),
          )
        ],
      ),
    );
  }

  Widget _buildMenuItem(IconData icon, String title, VoidCallback onTap, {bool isLogout = false}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10, offset: const Offset(0, 4)),
        ]
      ),
      child: ListTile(
        onTap: onTap,
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isLogout ? Colors.red.withOpacity(0.1) : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: isLogout ? Colors.red : const Color(0xFF64748B), size: 20),
        ),
        title: Text(
          title, 
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: isLogout ? Colors.red : const Color(0xFF0F172A))
        ),
        trailing: isLogout ? null : const Icon(Icons.arrow_forward_ios, size: 16, color: Color(0xFFCBD5E1)),
      ),
    );
  }
}
