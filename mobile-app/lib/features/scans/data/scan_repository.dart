import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';

class EquipmentScan {
  final int id;
  final String code;
  final String method;
  final int? taskId;
  final int? clientId;
  final String? clientName;
  final String? companyName;
  final String scannedAt;

  EquipmentScan({
    required this.id,
    required this.code,
    required this.method,
    this.taskId,
    this.clientId,
    this.clientName,
    this.companyName,
    required this.scannedAt,
  });

  factory EquipmentScan.fromJson(Map<String, dynamic> json) => EquipmentScan(
        id: json['id'],
        code: json['code'] ?? '',
        method: json['method'] ?? 'camera',
        taskId: json['task_id'],
        clientId: json['client_id'],
        clientName: json['client'] != null ? json['client']['full_name'] : null,
        companyName: json['company'] != null ? json['company']['name'] : null,
        scannedAt: json['scanned_at'] ?? '',
      );
}

final scanRepositoryProvider = Provider<ScanRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return ScanRepository(dio);
});

class ScanRepository {
  final Dio _dio;

  ScanRepository(this._dio);

  /// Registra el código escaneado de un equipo para una tarea.
  Future<EquipmentScan> registerScan({
    required String code,
    int? taskId,
    String method = 'camera',
    String? notes,
  }) async {
    try {
      final response = await _dio.post('/scans', data: {
        'code': code,
        if (taskId != null) 'task_id': taskId,
        'method': method,
        if (notes != null) 'notes': notes,
      });
      return EquipmentScan.fromJson(response.data['scan']);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Escaneos del día (o de la fecha indicada YYYY-MM-DD).
  Future<List<EquipmentScan>> getScans({String? date}) async {
    try {
      final response = await _dio.get('/scans', queryParameters: {
        if (date != null) 'date': date,
      });
      final List<dynamic> data = response.data['data'] ?? [];
      return data.map((json) => EquipmentScan.fromJson(json)).toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  Exception _handleError(DioException e) {
    final message = e.response?.data is Map
        ? (e.response?.data['message'] ?? 'Error de conexión')
        : 'Error de conexión';
    return Exception(message);
  }
}
