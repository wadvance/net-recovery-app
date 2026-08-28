import 'dart:async';
import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:dio/dio.dart' show HttpClientAdapter;

/// Adapter HTTP para móvil que crea un [HttpClient] nuevo por petición y lo
/// cierra al terminar (`persistentConnection = false`).
///
/// El hosting `netrecovery.gt.tc` sirve el reto anti-bot (aes.js) sobre una
/// conexión keep-alive que, de no cerrarse, envía una segunda respuesta
/// "unsolicited" que envenena el [HttpClient] compartido de Dio estándar y
/// crasha el isolate. Al aislar cada petición en su propio cliente y forzar el
/// cierre tras consumir la respuesta, el reto (y el reintento tras resolverlo)
/// no pueden corromper peticiones posteriores.
class FreshAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final httpClient = HttpClient()..idleTimeout = const Duration(seconds: 2);
    Future<HttpClientRequest> openF = httpClient.openUrl(options.method, options.uri);
    final ct = options.connectTimeout;
    if (ct != null && ct > Duration.zero) {
      openF = openF.timeout(ct, onTimeout: () {
        httpClient.close(force: true);
        throw DioException.connectionTimeout(requestOptions: options, timeout: ct);
      });
    }
    late final HttpClientRequest request;
    try {
      request = await openF;
    } catch (_) {
      httpClient.close(force: true);
      rethrow;
    }
    cancelFuture?.whenComplete(() => request.abort());

    options.headers.forEach((key, value) {
      if (value != null) {
        request.headers.set(key, value, preserveHeaderCase: options.preserveHeaderCase);
      }
    });
    request.followRedirects = options.followRedirects;
    request.maxRedirects = options.maxRedirects;
    request.persistentConnection = false;

    if (requestStream != null) {
      Future<dynamic> future = request.addStream(requestStream);
      final sendTimeout = options.sendTimeout;
      if (sendTimeout != null && sendTimeout > Duration.zero) {
        future = future.timeout(sendTimeout, onTimeout: () {
          request.abort();
          throw DioException.sendTimeout(
            requestOptions: options,
            timeout: sendTimeout,
          );
        });
      }
      await future;
    }

    Future<HttpClientResponse> future = request.close();
    final receiveTimeout = options.receiveTimeout ?? Duration.zero;
    if (receiveTimeout > Duration.zero) {
      future = future.timeout(receiveTimeout, onTimeout: () {
        request.abort();
        throw DioException.receiveTimeout(
          requestOptions: options,
          timeout: receiveTimeout,
        );
      });
    }

    late final HttpClientResponse responseStream;
    try {
      responseStream = await future;
    } on HttpException catch (e, s) {
      if (e.message.contains('Connection closed before full header was received')) {
        throw DioException.connectionError(
          requestOptions: options,
          reason: e.message,
          error: e,
          stackTrace: s,
        );
      }
      rethrow;
    }

    final headers = <String, List<String>>{};
    responseStream.headers.forEach((key, values) => headers[key] = values);

    String? httpVersion;
    try {
      httpVersion = (responseStream.headers as dynamic).protocolVersion as String?;
    } catch (_) {}

    return ResponseBody(
      responseStream.cast<Uint8List>(),
      responseStream.statusCode,
      headers: headers,
      isRedirect: responseStream.isRedirect || responseStream.redirects.isNotEmpty,
      redirects: responseStream.redirects
          .map((e) => RedirectRecord(e.statusCode, e.method, e.location))
          .toList(),
      statusMessage: responseStream.reasonPhrase,
      onClose: httpClient.close,
    );
  }

  @override
  void close({bool force = false}) {}
}
