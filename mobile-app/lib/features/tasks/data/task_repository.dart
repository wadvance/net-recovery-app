import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/models/task_model.dart';
import '../../../core/network/api_client.dart';

final taskRepositoryProvider = Provider<TaskRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return TaskRepository(dio);
});

class TaskRepository {
  final Dio _dio;

  TaskRepository(this._dio);

  Future<List<TaskModel>> getMyTasks({String? status}) async {
    try {
      final response = await _dio.get('/my-tasks', queryParameters: {
        if (status != null) 'status': status,
      });

      final List<dynamic> data = response.data['data'] ?? response.data;
      return data.map((json) => TaskModel.fromJson(json)).toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<TaskModel>> getMyTasksByDate(String date) async {
    try {
      final response = await _dio.get('/my-tasks/$date');
      final List<dynamic> data = response.data;
      return data.map((json) => TaskModel.fromJson(json)).toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<TaskModel> getTask(int taskId) async {
    try {
      final response = await _dio.get('/tasks/$taskId');
      return TaskModel.fromJson(response.data);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> startTask(int taskId) async {
    try {
      await _dio.put('/tasks/$taskId/start');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> completeTask(int taskId, {String? notes, String? signature}) async {
    try {
      await _dio.put('/tasks/$taskId/complete', data: {
        if (notes != null) 'notes': notes,
        if (signature != null) 'signature': signature,
      });
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> failTask(int taskId, String reason) async {
    try {
      await _dio.put('/tasks/$taskId/fail', data: {
        'reason': reason,
      });
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> acknowledgeTask(int taskId) async {
    try {
      await _dio.put('/tasks/$taskId/acknowledge');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> addEvidence({
    required int taskId,
    required String filePath,
    required String type,
    String? description,
    double? latitude,
    double? longitude,
  }) async {
    try {
      final formData = FormData.fromMap({
        'file': await MultipartFile.fromFile(filePath),
        'type': type,
        if (description != null) 'description': description,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      });

      await _dio.post('/tasks/$taskId/evidence', data: formData);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<TaskCommentModel>> getComments(int taskId) async {
    try {
      final response = await _dio.get('/tasks/$taskId/comments');
      final List<dynamic> data = response.data;
      return data.map((json) => TaskCommentModel.fromJson(json)).toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> addComment(int taskId, String comment, {bool isInternal = false}) async {
    try {
      await _dio.post('/tasks/$taskId/comments', data: {
        'comment': comment,
        'is_internal': isInternal,
      });
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getMyRoute({String? date}) async {
    try {
      final response = await _dio.get('/my-route', queryParameters: {
        if (date != null) 'date': date,
      });
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> optimizeRoute(String date) async {
    try {
      await _dio.post('/my-route/optimize', data: {'date': date});
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getMyStats() async {
    try {
      final response = await _dio.get('/my-dashboard/stats');
      return response.data;
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