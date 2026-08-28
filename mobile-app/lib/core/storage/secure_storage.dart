import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

class SecureStorage {
  final FlutterSecureStorage _storage;

  SecureStorage()
      : _storage = const FlutterSecureStorage(
          aOptions: AndroidOptions(
            encryptedSharedPreferences: true,
          ),
          iOptions: IOSOptions(
            accessibility: KeychainAccessibility.first_unlock_this_device,
          ),
        );

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'user_data';
  static const String _refreshTokenKey = 'refresh_token';
  static const String _antibotCookieKey = 'antibot_test_cookie';
  static const String _antibotTsKey = 'antibot_test_ts';

  Future<void> saveToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _tokenKey);
  }

  Future<void> saveUser(String userJson) async {
    await _storage.write(key: _userKey, value: userJson);
  }

  Future<String?> getUser() async {
    return await _storage.read(key: _userKey);
  }

  Future<void> saveRefreshToken(String token) async {
    await _storage.write(key: _refreshTokenKey, value: token);
  }

  Future<String?> getRefreshToken() async {
    return await _storage.read(key: _refreshTokenKey);
  }

  Future<void> deleteAll() async {
    await _storage.deleteAll();
  }

  Future<bool> hasToken() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> saveAntibotCookie(String cookie) async {
    await _storage.write(key: _antibotCookieKey, value: cookie);
    await _storage.write(key: _antibotTsKey, value: DateTime.now().toIso8601String());
  }

  Future<String?> getAntibotCookie() async {
    final cookie = await _storage.read(key: _antibotCookieKey);
    final ts = await _storage.read(key: _antibotTsKey);
    if (cookie == null || ts == null) return null;
    final t = DateTime.tryParse(ts);
    if (t == null) return null;
    if (DateTime.now().difference(t) > const Duration(hours: 5)) return null;
    return cookie;
  }

  Future<void> clearAntibotCookie() async {
    await _storage.delete(key: _antibotCookieKey);
    await _storage.delete(key: _antibotTsKey);
  }
}
