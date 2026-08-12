import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AppConfig {
  static late FlutterSecureStorage _secureStorage;

  static Future<void> init() async {
    _secureStorage = const FlutterSecureStorage();
  }

  static FlutterSecureStorage get secureStorage => _secureStorage;

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://netrecovery.unaux.com/api/v1',
  );

  static const String appName = 'Equipment Recovery';
  static const String appVersion = '1.0.0';

  // Google Maps API Key
  static const String googleMapsApiKey = String.fromEnvironment(
    'GOOGLE_MAPS_API_KEY',
    defaultValue: '',
  );
}