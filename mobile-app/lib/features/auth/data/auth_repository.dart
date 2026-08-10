import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/models/user_model.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final dio = ref.watch(dioProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthRepository(dio, storage);
});

class AuthRepository {
  final Dio _dio;
  final SecureStorage _storage;

  AuthRepository(this._dio, this._storage);

  Future<AuthResponse> login({
    required String email,
    required String password,
    String? deviceName,
  }) async {
    try {
      final response = await _dio.post('/login', data: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      });

      final authResponse = AuthResponse.fromJson(response.data);

      await _storage.saveToken(authResponse.token);
      await _storage.saveUser(jsonEncode(authResponse.user.toJson()));

      return authResponse;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<UserModel?> getStoredUser() async {
    final userJson = await _storage.getUser();
    if (userJson != null) {
      return UserModel.fromJson(jsonDecode(userJson));
    }
    return null;
  }

  Future<bool> isAuthenticated() async {
    return await _storage.hasToken();
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } catch (_) {
      // Ignore errors during logout
    } finally {
      await _storage.deleteAll();
    }
  }

  Future<UserModel> updateProfile({
    String? name,
    String? phone,
    String? avatar,
  }) async {
    try {
      final response = await _dio.put('/profile', data: {
        if (name != null) 'name': name,
        if (phone != null) 'phone': phone,
        if (avatar != null) 'avatar': avatar,
      });

      final user = UserModel.fromJson(response.data);
      await _storage.saveUser(jsonEncode(user.toJson()));
      return user;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      await _dio.put('/password', data: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      });
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  String _handleError(DioException e) {
    if (e.response?.data != null) {
      final data = e.response!.data;
      if (data['errors'] != null) {
        final errors = data['errors'] as Map<String, dynamic>;
        return errors.values.first[0].toString();
      }
      if (data['message'] != null) {
        return data['message'];
      }
    }
    return 'Error de conexión. Intenta de nuevo.';
  }
}

class AuthResponse {
  final UserModel user;
  final String token;
  final String tokenType;

  AuthResponse({
    required this.user,
    required this.token,
    required this.tokenType,
  });

  factory AuthResponse.fromJson(Map<String, dynamic> json) {
    return AuthResponse(
      user: UserModel.fromJson(json['user']),
      token: json['token'],
      tokenType: json['token_type'] ?? 'Bearer',
    );
  }
}

// Auth state
final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final repository = ref.watch(authRepositoryProvider);
  return AuthNotifier(repository);
});

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _repository;

  AuthNotifier(this._repository) : super(AuthState.initial()) {
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    state = state.copyWith(isLoading: true);
    final isAuth = await _repository.isAuthenticated();
    if (isAuth) {
      final user = await _repository.getStoredUser();
      state = AuthState(
        isAuthenticated: true,
        user: user,
        isLoading: false,
      );
    } else {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> login({
    required String email,
    required String password,
    String? deviceName,
  }) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _repository.login(
        email: email,
        password: password,
        deviceName: deviceName,
      );
      state = AuthState(
        isAuthenticated: true,
        user: response.user,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = AuthState.initial();
  }

  void clearError() {
    state = state.copyWith(error: null);
  }
}

class AuthState {
  final bool isAuthenticated;
  final UserModel? user;
  final bool isLoading;
  final String? error;

  AuthState({
    required this.isAuthenticated,
    this.user,
    required this.isLoading,
    this.error,
  });

  factory AuthState.initial() {
    return AuthState(
      isAuthenticated: false,
      isLoading: true,
    );
  }

  AuthState copyWith({
    bool? isAuthenticated,
    UserModel? user,
    bool? isLoading,
    String? error,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      user: user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}