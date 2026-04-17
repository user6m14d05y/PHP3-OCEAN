import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'screens/onboarding_screen.dart';
import 'screens/main_wrapper.dart';
import 'screens/login_screen.dart';
import 'services/auth_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await dotenv.load(fileName: ".env");

  final prefs = await SharedPreferences.getInstance();
  final isFirstLaunch = prefs.getBool('is_first_launch') ?? true;

  // Kiểm tra trạng thái đăng nhập từ SecureStorage (thay thế SharedPreferences cũ)
  final isLoggedIn = await AuthService.isLoggedIn();

  runApp(MyApp(
    isFirstLaunch: isFirstLaunch,
    isLoggedIn: isLoggedIn,
  ));
}

class MyApp extends StatelessWidget {
  final bool isFirstLaunch;
  final bool isLoggedIn;
  const MyApp({super.key, required this.isFirstLaunch, required this.isLoggedIn});

  @override
  Widget build(BuildContext context) {
    // Xác định màn hình khởi động:
    // 1. Lần đầu mở app → Onboarding
    // 2. Đã đăng nhập → MainWrapper (thẳng vào trang chủ)
    // 3. Chưa đăng nhập → LoginScreen
    Widget homeScreen;
    if (isFirstLaunch) {
      homeScreen = const OnboardingScreen();
    } else if (isLoggedIn) {
      homeScreen = const MainWrapper();
    } else {
      homeScreen = const LoginScreen();
    }

    return MaterialApp(
      title: 'Ocean Shop',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0EA5E9)),
        useMaterial3: true,
      ),
      home: homeScreen,
    );
  }
}
