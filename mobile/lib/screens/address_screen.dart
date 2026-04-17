import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

const String kBaseUrl = 'http://10.0.2.2:8383/api';

class AddressScreen extends StatefulWidget {
  final bool isSelecting;
  const AddressScreen({super.key, this.isSelecting = false});

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  List<dynamic> addresses = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    fetchAddresses();
  }

  Future<void> fetchAddresses() async {
    setState(() => isLoading = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      final res = await http.get(
        Uri.parse('$kBaseUrl/profile/addresses'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (mounted) {
          setState(() {
            addresses = data['data'] ?? [];
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

  Future<void> deleteAddress(int id) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('access_token');
      await http.delete(
        Uri.parse('$kBaseUrl/profile/addresses/$id'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
      );
      fetchAddresses();
    } catch (e) {
      // Bỏ qua lỗi
    }
  }

  void _showAddAddressModal() {
    final nameCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final addressCtrl = TextEditingController();

    List<dynamic> provinces = [];
    List<dynamic> districts = [];
    List<dynamic> wards = [];

    String? selectedProvCode;
    String? selectedProvName;
    String? selectedDistCode;
    String? selectedDistName;
    String? selectedWardCode;
    String? selectedWardName;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) {
        return StatefulBuilder(
          builder: (BuildContext context, StateSetter setModalState) {
            
            if (provinces.isEmpty) {
              http.get(
                Uri.parse('https://online-gateway.ghn.vn/shiip/public-api/master-data/province'), 
                headers: {'Token': dotenv.env['TOKEN_GHN'] ?? ''}
              ).then((res) {
                if (res.statusCode == 200) setModalState(() => provinces = jsonDecode(res.body)['data'] ?? []);
              });
            }

            void onProvChanged(dynamic val) {
              final pv = provinces.firstWhere((p) => p['ProvinceID'].toString() == val.toString());
              setModalState(() {
                selectedProvCode = val.toString();
                selectedProvName = pv['ProvinceName'];
                districts = []; wards = [];
                selectedDistCode = null; selectedWardCode = null;
              });
              http.get(
                Uri.parse('https://online-gateway.ghn.vn/shiip/public-api/master-data/district?province_id=$val'), 
                headers: {'Token': dotenv.env['TOKEN_GHN'] ?? ''}
              ).then((res) {
                if (res.statusCode == 200) setModalState(() => districts = jsonDecode(res.body)['data'] ?? []);
              });
            }

            void onDistChanged(dynamic val) {
              final dt = districts.firstWhere((d) => d['DistrictID'].toString() == val.toString());
              setModalState(() {
                selectedDistCode = val.toString();
                selectedDistName = dt['DistrictName'];
                wards = []; selectedWardCode = null;
              });
              http.get(
                Uri.parse('https://online-gateway.ghn.vn/shiip/public-api/master-data/ward?district_id=$val'), 
                headers: {'Token': dotenv.env['TOKEN_GHN'] ?? ''}
              ).then((res) {
                if (res.statusCode == 200) setModalState(() => wards = jsonDecode(res.body)['data'] ?? []);
              });
            }

            void onWardChanged(dynamic val) {
              final wd = wards.firstWhere((w) => w['WardCode'].toString() == val.toString());
              setModalState(() {
                selectedWardCode = val.toString();
                selectedWardName = wd['WardName'];
              });
            }

            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, left: 16, right: 16, top: 16),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Thêm Sổ Địa Chỉ Mới', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 16),
                    TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Tên người nhận')),
                    TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Số điện thoại')),
                    DropdownButtonFormField(
                      decoration: const InputDecoration(labelText: 'Tỉnh/Thành phố'),
                      value: selectedProvCode,
                      items: provinces.map((p) => DropdownMenuItem(value: p['ProvinceID'].toString(), child: Text(p['ProvinceName']))).toList(),
                      onChanged: onProvChanged,
                    ),
                    DropdownButtonFormField(
                      decoration: const InputDecoration(labelText: 'Quận/Huyện'),
                      value: selectedDistCode,
                      items: districts.map((d) => DropdownMenuItem(value: d['DistrictID'].toString(), child: Text(d['DistrictName']))).toList(),
                      onChanged: onDistChanged,
                    ),
                    DropdownButtonFormField(
                      decoration: const InputDecoration(labelText: 'Phường/Xã'),
                      value: selectedWardCode,
                      items: wards.map((w) => DropdownMenuItem(value: w['WardCode'].toString(), child: Text(w['WardName']))).toList(),
                      onChanged: onWardChanged,
                    ),
                    TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Số nhà, Tên đường')),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0EA5E9), padding: const EdgeInsets.symmetric(vertical: 14)),
                        onPressed: () async {
                          if (selectedWardCode == null || nameCtrl.text.isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng điền đủ thông tin')));
                            return;
                          }
                          Navigator.pop(context); // Close modal
                          showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));
                          
                          try {
                            final prefs = await SharedPreferences.getInstance();
                            final token = prefs.getString('access_token');
                            await http.post(
                              Uri.parse('$kBaseUrl/profile/addresses'),
                              headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer $token'},
                              body: jsonEncode({
                                'recipient_name': nameCtrl.text,
                                'phone': phoneCtrl.text,
                                'province': selectedProvName,
                                'district': selectedDistName,
                                'ward': selectedWardName,
                                'province_code': selectedProvCode,
                                'district_code': selectedDistCode,
                                'ward_code': selectedWardCode,
                                'address_line': addressCtrl.text,
                                'is_default': false
                              })
                            );
                            if (mounted) {
                              Navigator.pop(context); // Close spinner
                              fetchAddresses();
                            }
                          } catch (e) {
                            if (mounted) Navigator.pop(context);
                          }
                        },
                        child: const Text('Lưu Địa Chỉ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            );
          }
        );
      }
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Sổ địa chỉ', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0EA5E9)),
      ),
      body: isLoading 
        ? const Center(child: CircularProgressIndicator(color: Color(0xFF0EA5E9))) 
        : addresses.isEmpty 
          ? const Center(child: Text('Chưa có địa chỉ nào lưu trữ.', style: TextStyle(color: Colors.grey)))
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: addresses.length,
              itemBuilder: (context, index) {
                final addr = addresses[index];
                final isDefault = addr['is_default'] == 1 || addr['is_default'] == true;
                
                return GestureDetector(
                  onTap: () {
                    if (widget.isSelecting) {
                      Navigator.pop(context, addr);
                    }
                  },
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white, 
                      borderRadius: BorderRadius.circular(12), 
                      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10)]
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Text(addr['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                              if (isDefault)
                                Container(
                                  margin: const EdgeInsets.only(left: 8),
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(color: Colors.red.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
                                  child: const Text('Mặc định', style: TextStyle(color: Colors.red, fontSize: 10, fontWeight: FontWeight.bold)),
                                )
                            ],
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline, color: Colors.grey),
                            onPressed: () => deleteAddress(addr['address_id'] ?? addr['id']),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          )
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(addr['phone'] ?? '', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                      const SizedBox(height: 8),
                      Text('${addr['address_line']}, ${addr['ward']}, ${addr['district']}, ${addr['province']}', style: const TextStyle(color: Color(0xFF334155), height: 1.5, fontSize: 13)),
                    ]
                  )
                ),
              );
            }
          ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: ElevatedButton(
            onPressed: _showAddAddressModal,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0EA5E9),
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))
            ),
            child: const Text('Thêm địa chỉ mới', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
          ),
        ),
      ),
    );
  }
}
