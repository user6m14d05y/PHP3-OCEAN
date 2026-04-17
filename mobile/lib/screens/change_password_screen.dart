import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const String kBaseUrl = 'http://10.0.2.2:8383/api';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final currentCtrl = TextEditingController();
  final newCtrl = TextEditingController();
  final confirmCtrl = TextEditingController();

  Future<void> submit() async {
    if (newCtrl.text != confirmCtrl.text) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Xác nhận mật khẩu không khớp!', style: TextStyle(color: Colors.white)), backgroundColor: Colors.red));
      return;
    }

    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));

    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      final res = await http.put(
        Uri.parse('$kBaseUrl/profile/password'),
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
        body: jsonEncode({
          'current_password': currentCtrl.text,
          'new_password': newCtrl.text,
          'new_password_confirmation': confirmCtrl.text,
        })
      );
      
      final data = jsonDecode(res.body);
      if (mounted) Navigator.pop(context); // Close spinner
      
      if (res.statusCode == 200 && data['status'] == 'success') {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đổi mật khẩu thành công!'), backgroundColor: Colors.green));
          Navigator.pop(context);
        }
      } else {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message'] ?? 'Lỗi thay đổi mật khẩu!'), backgroundColor: Colors.red));
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối máy chủ!'), backgroundColor: Colors.red));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Đổi mật khẩu', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0EA5E9)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10)]),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Mật khẩu hiện tại', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              TextField(
                controller: currentCtrl,
                obscureText: true,
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14)
                ),
              ),
              const SizedBox(height: 20),
              
              const Text('Mật khẩu mới', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              TextField(
                controller: newCtrl,
                obscureText: true,
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14)
                ),
              ),
              const SizedBox(height: 20),

              const Text('Xác nhận mật khẩu mới', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              TextField(
                controller: confirmCtrl,
                obscureText: true,
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14)
                ),
              ),
              const SizedBox(height: 32),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: submit,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0EA5E9), padding: const EdgeInsets.symmetric(vertical: 16), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  child: const Text('Cập Nhật', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                ),
              )
            ],
          )
        ),
      ),
    );
  }
}
