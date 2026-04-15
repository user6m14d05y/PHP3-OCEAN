import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:image_picker/image_picker.dart';

const String kBaseUrl = 'http://localhost:8383/api';

class EditProfileScreen extends StatefulWidget {
  final Map<String, dynamic> userData;
  const EditProfileScreen({super.key, required this.userData});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  late TextEditingController _nameCtrl;
  late TextEditingController _phoneCtrl;
  bool _isSaving = false;
  XFile? _pickedImage;
  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.userData['full_name'] ?? widget.userData['name'] ?? '');
    _phoneCtrl = TextEditingController(text: widget.userData['phone'] ?? '');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final picked = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 80, maxWidth: 800);
    if (picked != null) setState(() => _pickedImage = picked);
  }

  Future<void> _save() async {
    if (_nameCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng nhập họ tên!'), backgroundColor: Colors.orange));
      return;
    }
    setState(() => _isSaving = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');

      // Use multipart request to support avatar upload
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('$kBaseUrl/profile'),
      );
      request.headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      });
      request.fields['full_name'] = _nameCtrl.text.trim();
      request.fields['phone'] = _phoneCtrl.text.trim();

      if (_pickedImage != null) {
        request.files.add(await http.MultipartFile.fromPath('avatar', _pickedImage!.path));
      }

      final streamedRes = await request.send();
      final response = await http.Response.fromStream(streamedRes);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Cập nhật thành công!'), backgroundColor: Colors.green));
          Navigator.pop(context, data['data']); // Return updated user data
        }
      } else {
        final data = jsonDecode(response.body);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(data['message'] ?? 'Cập nhật thất bại!'),
            backgroundColor: Colors.red,
          ));
        }
      }
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối!'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final avatar = widget.userData['avatar_url'];
    final avatarUrl = avatar != null
      ? (avatar.startsWith('http') ? avatar : 'http://localhost:8383/api/image-proxy?path=$avatar')
      : null;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Chỉnh sửa hồ sơ', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0EA5E9)),
        actions: [
          TextButton(
            onPressed: _isSaving ? null : _save,
            child: _isSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF0EA5E9)))
              : const Text('Lưu', style: TextStyle(color: Color(0xFF0EA5E9), fontWeight: FontWeight.bold, fontSize: 16)),
          )
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Avatar
            Center(
              child: Stack(
                children: [
                  GestureDetector(
                    onTap: _pickImage,
                    child: Container(
                      width: 100, height: 100,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFF0EA5E9), width: 3),
                      ),
                      child: ClipOval(
                        child: _pickedImage != null
                          ? Image.file(File(_pickedImage!.path), fit: BoxFit.cover)
                          : (avatarUrl != null
                              ? Image.network(avatarUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _defaultAvatar())
                              : _defaultAvatar()),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 0, right: 0,
                    child: GestureDetector(
                      onTap: _pickImage,
                      child: Container(
                        padding: const EdgeInsets.all(6),
                        decoration: const BoxDecoration(color: Color(0xFF0EA5E9), shape: BoxShape.circle),
                        child: const Icon(Icons.camera_alt, size: 16, color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            const Text('Nhấn để đổi ảnh', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
            const SizedBox(height: 24),

            // Form fields
            _buildCard(children: [
              _buildField(label: 'Họ và tên', controller: _nameCtrl, hint: 'Nhập họ tên của bạn', icon: Icons.person_outline),
              const Divider(color: Color(0xFFF1F5F9), height: 1),
              _buildField(label: 'Số điện thoại', controller: _phoneCtrl, hint: 'Nhập số điện thoại', icon: Icons.phone_outlined, type: TextInputType.phone),
            ]),
            const SizedBox(height: 16),

            // Email (read-only)
            _buildCard(children: [
              _buildReadOnly(label: 'Email', value: widget.userData['email'] ?? '', icon: Icons.email_outlined),
            ]),

            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isSaving ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0EA5E9),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isSaving
                  ? const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                      SizedBox(width: 10),
                      Text('Đang lưu...', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    ])
                  : const Text('Lưu thay đổi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _defaultAvatar() => Container(
    color: const Color(0xFFE0F2FE),
    child: const Icon(Icons.person, size: 50, color: Color(0xFF0EA5E9)),
  );

  Widget _buildCard({required List<Widget> children}) => Container(
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 10)]),
    child: Column(children: children),
  );

  Widget _buildField({required String label, required TextEditingController controller, required String hint, required IconData icon, TextInputType type = TextInputType.text}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF94A3B8), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500)),
                const SizedBox(height: 4),
                TextField(
                  controller: controller,
                  keyboardType: type,
                  style: const TextStyle(fontSize: 15, color: Color(0xFF0F172A), fontWeight: FontWeight.w500),
                  decoration: InputDecoration(
                    hintText: hint,
                    hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReadOnly({required String label, required String value, required IconData icon}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF94A3B8), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500)),
                const SizedBox(height: 4),
                Text(value, style: const TextStyle(fontSize: 15, color: Color(0xFF64748B))),
              ],
            ),
          ),
          const Icon(Icons.lock_outline, size: 14, color: Color(0xFFCBD5E1)),
        ],
      ),
    );
  }
}
