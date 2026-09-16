import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'aes128.dart';
import '../storage/secure_storage.dart';

/// Helper para resolver el reto anti-bot (aes.js / slowAES) de freehosting.
class AntiBotSolver {
  final SecureStorage storage;
  AntiBotSolver(this.storage);

  // ignore: unused_field
  static const _cookieKey = 'antibot_test_cookie';
  // ignore: unused_field
  static const _tsKey = 'antibot_test_ts';
  // ignore: unused_field
  static const _lifetime = Duration(hours: 5);

  static bool isChallenge(String body) {
    return body.contains('aes.js') && body.contains('slowAES');
  }

  static String? solveFromString(String html) {
    final re = RegExp(r'a=toNumbers\("([0-9a-f]+)"\),b=toNumbers\("([0-9a-f]+)"\),c=toNumbers\("([0-9a-f]+)"\)');
    final m = re.firstMatch(html);
    if (m == null) return null;
    final key = Uint8List.fromList(_hex(m.group(1)!));
    final iv = Uint8List.fromList(_hex(m.group(2)!));
    final ct = Uint8List.fromList(_hex(m.group(3)!));
    final pt = Aes128.cbcDecrypt(key, iv, ct);
    return _toHex(pt);
  }

  static List<int> _hex(String s) {
    final out = <int>[];
    for (var i = 0; i + 1 < s.length; i += 2) {
      out.add(int.parse(s.substring(i, i + 2), radix: 16));
    }
    return out;
  }

  static String _toHex(Uint8List b) {
    final sb = StringBuffer();
    for (final v in b) {
      sb.write(v.toRadixString(16).padLeft(2, '0'));
    }
    return sb.toString();
  }

  Future<String?> loadCookie() => storage.getAntibotCookie();

  Future<void> saveCookie(String cookie) => storage.saveAntibotCookie(cookie);
}

/// Interceptor funcional de Dio que resuelve el anti-bot y reintenta.
Interceptor buildAntibotInterceptor(SecureStorage storage, Dio dio) {
  var retried = false;
  return InterceptorsWrapper(
    onRequest: (options, handler) async {
      final solver = AntiBotSolver(storage);
      final cookie = await solver.loadCookie();
      if (cookie != null) {
        options.headers['Cookie'] = '__test=$cookie';
      }
      return handler.next(options);
    },
    onResponse: (response, handler) async {
      final data = response.data;
      final body = data is String ? data : data?.toString() ?? '';
      if (!AntiBotSolver.isChallenge(body)) {
        return handler.next(response);
      }
      final solver = AntiBotSolver(storage);
      final cookie = AntiBotSolver.solveFromString(body);
      if (cookie == null) {
        return handler.reject(DioException(
          requestOptions: response.requestOptions,
          response: response,
          error: 'anti-bot challenge not parseable',
        ));
      }
      await solver.saveCookie(cookie);
      retried = retried; // noop
      final opts = response.requestOptions;
      if (opts.extra['__antibot_retried'] == true) {
        return handler.reject(DioException(
          requestOptions: opts, response: response, error: 'anti-bot retry loop'));
      }
      opts.headers['Cookie'] = '__test=$cookie';
      opts.extra['__antibot_retried'] = true;
      try {
        final retry = await dio.fetch(opts);
        return handler.resolve(retry);
      } catch (e) {
        return handler.reject(DioException(requestOptions: opts, error: e));
      }
    },
    onError: (error, handler) => handler.next(error),
  );
}
