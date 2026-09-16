import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:recovery_app/core/network/aes128.dart';

String? solve(String html) {
  final m = RegExp(r'a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)').firstMatch(html);
  if (m == null) return null;
  Uint8List hx(String s) {
    final out = <int>[];
    for (var i = 0; i + 1 < s.length; i += 2) {
      out.add(int.parse(s.substring(i, i + 2), radix: 16));
    }
    return Uint8List.fromList(out);
  }
  final pt = Aes128.cbcDecrypt(hx(m[1]!), hx(m[2]!), hx(m[3]!));
  final sb = StringBuffer();
  for (final b in pt) {
    sb.write(b.toRadixString(16).padLeft(2, '0'));
  }
  return sb.toString();
}

bool isChallenge(String s) => s.contains('aes.js');

void main() async {
  final dio = Dio(BaseOptions(
    baseUrl: 'https://netrecovery.gt.tc',
    responseType: ResponseType.json,
    connectTimeout: const Duration(seconds: 20),
    receiveTimeout: const Duration(seconds: 20),
    headers: {
      'User-Agent': 'Mozilla/5.0 (Linux; Android 10; Mobile)',
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Connection': 'close',
    },
  ));
  (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
    final c = HttpClient();
    c.idleTimeout = const Duration(milliseconds: 50);
    return c;
  };
  final payload = {'email': 'barj23535@gmail.com', 'password': 'TempPass123!', 'device_name': 'deviceId'};

  stderr.writeln('r1: POST login NO cookie (expect challenge)...');
  late dynamic data1;
  try {
    final res = await dio.post('/api/v1/login', data: payload);
    data1 = res.data.toString();
  } catch (e) {
    data1 = e is DioException ? (e.response?.data?.toString() ?? e.toString()) : e.toString();
  }
  stderr.writeln('r1 challenge=${isChallenge(data1)} snippet=${data1.substring(0, data1.length.clamp(0, 50))}');
  final cookie = solve(data1);
  stderr.writeln('solved: $cookie');

  stderr.writeln('r2: SAME dio, POST login WITH cookie (expect clean JSON)...');
  try {
    final res = await dio.post('/api/v1/login', data: payload, options: Options(headers: {'Cookie': '__test=$cookie'}));
    final d2 = res.data;
    stderr.writeln('r2 status=${res.statusCode} dataIsMap=${d2 is Map} keys=${d2 is Map ? d2.keys.toList() : "n/a"} snippet=${d2.toString().substring(0, d2.toString().length.clamp(0, 120))}');
    if (res.statusCode == 200 && d2 is Map && d2.containsKey('token')) stderr.writeln('SUCCESS');
  } catch (e) {
    stderr.writeln('r2 threw: ${e.runtimeType} ${e.toString().trim().substring(0, 100)}');
  }
  exit(0);
}
