import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:recovery_app/core/network/aes128.dart';

Uint8List hx(String s) {
  final out = <int>[];
  for (var i = 0; i + 1 < s.length; i += 2) {
    out.add(int.parse(s.substring(i, i + 2), radix: 16));
  }
  return Uint8List.fromList(out);
}

String? solve(String html) {
  final m = RegExp(r'a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)').firstMatch(html);
  if (m == null) return null;
  final pt = Aes128.cbcDecrypt(hx(m[1]!), hx(m[2]!), hx(m[3]!));
  final sb = StringBuffer();
  for (final b in pt) {
    sb.write(b.toRadixString(16).padLeft(2, '0'));
  }
  return sb.toString();
}

Future<String> probeChallenge() async {
  final client = HttpClient()..idleTimeout = const Duration(milliseconds: 50);
  final req = await client.getUrl(Uri.parse('https://netrecovery.gt.tc/'));
  req.headers.set('User-Agent', 'Mozilla/5.0 (Linux; Android 10; Mobile)');
  final resp = await req.close();
  final body = await resp.transform(utf8.decoder).join();
  client.close(force: true);
  return body;
}

Future<Map> dioLogin(String cookie) async {
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
  final res = await dio.post('/api/v1/login',
    data: {'email': 'barj23535@gmail.com', 'password': 'TempPass123!', 'device_name': 'deviceId'},
    options: Options(headers: {'Cookie': '__test=$cookie'}),
  );
  if (res.data is Map) return res.data as Map;
  return {'_raw': res.data};
}

void main() async {
  stderr.writeln('1. raw probe challenge...');
  final body = await probeChallenge();
  stderr.writeln('   challenge=${body.contains('aes.js')}');
  final cookie = solve(body);
  stderr.writeln('   solved: $cookie');
  stderr.writeln('2. Dio login with cookie (expect clean JSON)...');
  final map = await dioLogin(cookie!);
  stderr.writeln('   resultKeys=${map.keys.toList()} hasToken=${map.containsKey('token')} hasUser=${map.containsKey('user') && (map['user'] is Map)}');
  if (map.containsKey('token') || (map['user'] is Map)) stderr.writeln('SUCCESS');
  exit(0);
}
