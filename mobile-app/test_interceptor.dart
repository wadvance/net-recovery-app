import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:recovery_app/core/network/aes128.dart';

String? solve(String html) {
  final m = RegExp(r'a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)').firstMatch(html);
  if (m == null) return null;
  Uint8List hx(String s) {
    final out = <int>[];
    for (var i = 0; i + 1 < s.length; i += 2) out.add(int.parse(s.substring(i, i + 2), radix: 16));
    return Uint8List.fromList(out);
  }
  final pt = Aes128.cbcDecrypt(hx(m[1]!), hx(m[2]!), hx(m[3]!));
  final sb = StringBuffer();
  for (final b in pt) sb.write(b.toRadixString(16).padLeft(2, '0'));
  return sb.toString();
}

bool isChallenge(String s) => s.contains('aes.js');

Dio newAuth(String? cookie) => Dio(BaseOptions(
      baseUrl: 'https://netrecovery.gt.tc',
      responseType: ResponseType.plain,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 20),
      headers: {
        'User-Agent': 'Mozilla/5.0 (Linux; Android 10; Mobile)',
        'Accept': 'text/html',
        if (cookie != null) 'Cookie': '__test=$cookie',
      },
    ));

void main() async {
  final payload = {'email': 'barj23535@gmail.com', 'password': 'TempPass123!', 'device_name': 'deviceId'};
  String? cookie;
  for (var i = 1; i <= 8; i++) {
    try {
      final res = await newAuth(cookie).post('/api/v1/login', data: FormData.fromMap(payload));
      final body = res.data?.toString() ?? '';
      stderr.writeln('try $i: status=${res.statusCode} challenge=${isChallenge(body)} len=${body.length} body=${body.substring(0, body.length.clamp(0, 90))}');
      if (isChallenge(body)) { cookie = solve(body); stderr.writeln('   solved: $cookie'); continue; }
      if (res.statusCode == 200 && body.contains('"token')) { stderr.writeln('SUCCESS'); exit(0); }
    } on DioException catch (e) {
      stderr.writeln('try $i: DioException type=${e.type} status=${e.response?.statusCode} msg=${e.message?.substring(0,80)}');
    } catch (e) {
      stderr.writeln('try $i: ${e.runtimeType} ${e.toString().trim().substring(0,100)}');
    }
    await Future.delayed(Duration(milliseconds: 200));
  }
  stderr.writeln('ALL TRIES EXHAUSTED');
  exit(1);
}
