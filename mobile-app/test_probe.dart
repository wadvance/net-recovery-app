import 'dart:io';
import 'package:dio/dio.dart';

void main() async {
  final dio = Dio(BaseOptions(
    baseUrl: 'https://netrecovery.gt.tc',
    responseType: ResponseType.json,
    connectTimeout: const Duration(seconds: 20),
    receiveTimeout: const Duration(seconds: 20),
    headers: {'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json,*/*'},
  ));
  print('--- probe: GET / (no cookie, expect challenge HTML) ---');
  try {
    final r = await dio.get('/');
    print('onResponse: status=${r.statusCode} dataIsString=${r.data is String}');
  } catch (e) {
    if (e is DioException) {
      print('onError type=${e.type} status=${e.response?.statusCode} message=${e.message}');
      print('error=${e.error?.toString()}');
    } else {
      print('onError other: ${e.runtimeType} ${e.toString()}');
    }
  }
  exit(0);
}
