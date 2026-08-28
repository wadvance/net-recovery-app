import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:recovery_app/core/network/aes128.dart';

Uint8List hx(String s) {
  final out = <int>[];
  for (var i = 0; i + 1 < s.length; i += 2) out.add(int.parse(s.substring(i, i + 2), radix: 16));
  return Uint8List.fromList(out);
}

String? solve(String html) {
  final m = RegExp(r'a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)').firstMatch(html);
  if (m == null) return null;
  final pt = Aes128.cbcDecrypt(hx(m[1]!), hx(m[2]!), hx(m[3]!));
  final sb = StringBuffer();
  for (final b in pt) sb.write(b.toRadixString(16).padLeft(2, '0'));
  return sb.toString();
}

Future<({int status, String body, List<String> setCookies})> rawPost(String? cookie) async {
  final client = HttpClient()..idleTimeout = Duration(milliseconds: 50);
  final uri = Uri.parse('https://netrecovery.gt.tc/api/v1/login');
  final req = await client.postUrl(uri);
  req.headers.set('User-Agent', 'Mozilla/5.0 (Linux; Android 10; Mobile)');
  req.headers.set('Content-Type', 'application/json');
  if (cookie != null) req.headers.set('Cookie', '__test=$cookie');
  req.add(utf8.encode(jsonEncode({'email': 'barj23535@gmail.com', 'password': 'TempPass123!', 'device_name': 'deviceId'})));
  final resp = await req.close();
  final status = resp.statusCode;
  final setC = <String>[];
  for (final c in resp.cookies) setC.add('${c.name}=${c.value}');
  final body = await resp.transform(utf8.decoder).join();
  client.close(force: true);
  return (status: status, body: body, setCookies: setC);
}

void main() async {
  stderr.writeln('req1 (no cookie)...');
  final r1 = await rawPost(null);
  stderr.writeln('req1: status=${r1.status} challenge=${r1.body.contains("aes.js")} len=${r1.body.length}');
  final cookie = solve(r1.body);
  stderr.writeln('req1 solved: $cookie');
  stderr.writeln('req2 (with cookie)...');
  final r2 = await rawPost(cookie);
  stderr.writeln('req2: status=${r2.status} challenge=${r2.body.contains("aes.js")} len=${r2.body.length} body=${r2.body.substring(0, r2.body.length.clamp(0, 120))}');
  if (r2.status == 200 && r2.body.contains('"token')) stderr.writeln('SUCCESS');
  exit(0);
}
