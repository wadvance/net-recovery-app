import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/constants/app_colors.dart';
import '../data/scan_repository.dart';

/// Escáner de equipos con la cámara del celular.
///
/// Registra cada código detectado contra el backend ([ScanRepository]).
/// Si [taskId] se proporciona, el escaneo queda asociado a esa tarea
/// (cliente de la lista del Excel). Permite escanear varios equipos
/// seguidos sin salir de la pantalla.
class EquipmentScanScreen extends ConsumerStatefulWidget {
  final int? taskId;
  final String? clientName;

  const EquipmentScanScreen({
    super.key,
    this.taskId,
    this.clientName,
  });

  @override
  ConsumerState<EquipmentScanScreen> createState() =>
      _EquipmentScanScreenState();
}

class _EquipmentScanScreenState extends ConsumerState<EquipmentScanScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );

  bool _permissionGranted = false;
  bool _permissionDenied = false;
  bool _processing = false;
  int _sessionCount = 0;
  String? _lastCode;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _requestPermission());
  }

  Future<void> _requestPermission() async {
    final status = await Permission.camera.request();
    if (!mounted) return;
    if (status.isGranted) {
      setState(() {
        _permissionGranted = true;
        _permissionDenied = false;
      });
    } else if (status.isPermanentlyDenied) {
      setState(() => _permissionDenied = true);
    } else {
      setState(() => _permissionDenied = true);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_processing) return;
    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue;
      if (code == null || code.isEmpty) continue;
      if (code == _lastCode) continue; // mismo código pegado al lente
      await _register(code);
      break;
    }
  }

  Future<void> _register(String code) async {
    setState(() => _processing = true);
    try {
      await ref.read(scanRepositoryProvider).registerScan(
            code: code,
            taskId: widget.taskId,
            method: 'camera',
          );
      if (!mounted) return;
      setState(() {
        _lastCode = code;
        _sessionCount++;
      });
      HapticFeedback.heavyImpact();
      _showResult(
        success: true,
        title: 'Equipo registrado',
        message: 'Código: $code',
      );
    } catch (e) {
      if (!mounted) return;
      HapticFeedback.vibrate();
      _showResult(
        success: false,
        title: 'Error al registrar',
        message: e.toString().replaceFirst('Exception: ', ''),
      );
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  /// Registro manual por teclado (código dañado o ilegible).
  Future<void> _manualEntry() async {
    final controller = TextEditingController(text: _lastCode);
    final code = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Código manual'),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'Código del equipo',
            hintText: 'Ej. EQ-00123',
          ),
          onSubmitted: (value) => Navigator.pop(context, value),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Registrar'),
          ),
        ],
      ),
    );
    if (code != null && code.isNotEmpty) {
      await _register(code);
    }
  }

  void _showResult({
    required bool success,
    required String title,
    required String message,
  }) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: success ? AppColors.completed : AppColors.error,
        duration: const Duration(seconds: 2),
        behavior: SnackBarBehavior.floating,
        content: Row(
          children: [
            Icon(success ? Icons.check_circle : Icons.error, color: Colors.white),
            SizedBox(width: 8.w),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(title,
                      style: TextStyle(
                          fontWeight: FontWeight.bold, fontSize: 14.sp)),
                  Text(message, style: TextStyle(fontSize: 12.sp)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: Text(
          widget.clientName != null
              ? 'Escanear - ${widget.clientName}'
              : 'Escanear equipo',
          style: TextStyle(fontSize: 17.sp),
        ),
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        actions: [
          if (_sessionCount > 0)
            Center(
              child: Padding(
                padding: EdgeInsets.only(right: 16.w),
                child: Text(
                  '$_sessionCount escaneado(s)',
                  style: TextStyle(color: AppColors.completed, fontSize: 13.sp),
                ),
              ),
            ),
        ],
      ),
      body: Stack(
        children: [
          if (_permissionDenied)
            Center(
              child: Padding(
                padding: EdgeInsets.all(24.w),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.videocam_off, size: 64.sp, color: Colors.white70),
                    SizedBox(height: 16.h),
                    Text(
                      'Permiso de cámara denegado',
                      style: TextStyle(color: Colors.white, fontSize: 16.sp, fontWeight: FontWeight.bold),
                      textAlign: TextAlign.center,
                    ),
                    SizedBox(height: 8.h),
                    Text(
                      'Activa la cámara en Ajustes para poder escanear.',
                      style: TextStyle(color: Colors.white70, fontSize: 13.sp),
                      textAlign: TextAlign.center,
                    ),
                    SizedBox(height: 20.h),
                    ElevatedButton(
                      onPressed: () => openAppSettings(),
                      child: const Text('Abrir Ajustes'),
                    ),
                    SizedBox(height: 12.h),
                    OutlinedButton(
                      onPressed: _requestPermission,
                      style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54)),
                      child: const Text('Reintentar', style: TextStyle(color: Colors.white)),
                    ),
                  ],
                ),
              ),
            )
          else if (!_permissionGranted)
            const Center(child: CircularProgressIndicator(color: Colors.white))
          else
            MobileScanner(
              controller: _controller,
              onDetect: _onDetect,
              errorBuilder: (context, error, child) => Center(
                child: Text('Error de cámara: $error', style: const TextStyle(color: Colors.white)),
              ),
            ),
          // Marco guía
          Center(
            child: Container(
              width: 260.w,
              height: 160.h,
              decoration: BoxDecoration(
                border: Border.all(color: AppColors.primary, width: 3),
                borderRadius: BorderRadius.circular(12.r),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 110.h,
            child: Center(
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
                color: Colors.black54,
                child: Text(
                  _processing
                      ? 'Registrando...'
                      : 'Apunta la cámara al código del equipo',
                  style: TextStyle(color: Colors.white, fontSize: 14.sp),
                ),
              ),
            ),
          ),
          // Botones inferiores
          Positioned(
            left: 24.w,
            right: 24.w,
            bottom: 32.h,
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _processing ? null : _manualEntry,
                    icon: const Icon(Icons.keyboard, color: Colors.white),
                    label: const Text('Manual',
                        style: TextStyle(color: Colors.white)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: Colors.white54),
                      padding: EdgeInsets.symmetric(vertical: 12.h),
                    ),
                  ),
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _controller.toggleTorch(),
                    icon: const Icon(Icons.flashlight_on, color: Colors.white),
                    label: const Text('Linterna',
                        style: TextStyle(color: Colors.white)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: Colors.white54),
                      padding: EdgeInsets.symmetric(vertical: 12.h),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
