import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../core/constants/app_colors.dart';
import '../../../shared/models/task_model.dart';
import '../../tasks/data/task_repository.dart';

final mapTasksProvider = FutureProvider.autoDispose<List<TaskModel>>((ref) async {
  final repository = ref.watch(taskRepositoryProvider);
  final today = DateTime.now().toIso8601String().split('T')[0];
  return repository.getMyTasksByDate(today);
});

class MapScreen extends ConsumerStatefulWidget {
  const MapScreen({super.key});

  @override
  ConsumerState<MapScreen> createState() => _MapScreenState();
}

class _MapScreenState extends ConsumerState<MapScreen> {
  GoogleMapController? _controller;
  Set<Marker> _markers = {};
  LatLng? _currentPosition;

  @override
  Widget build(BuildContext context) {
    final tasksAsync = ref.watch(mapTasksProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mapa de Tareas'),
        actions: [
          IconButton(
            icon: const Icon(Icons.my_location),
            onPressed: _goToMyLocation,
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.invalidate(mapTasksProvider),
          ),
        ],
      ),
      body: tasksAsync.when(
        data: (tasks) {
          _updateMarkers(tasks);
          return GoogleMap(
            initialCameraPosition: CameraPosition(
              target: _currentPosition ?? const LatLng(-0.1807, -78.4678), // Quito default
              zoom: 12,
            ),
            markers: _markers,
            onMapCreated: (controller) {
              _controller = controller;
            },
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 60.sp, color: AppColors.error),
              SizedBox(height: 16.h),
              Text(error.toString()),
            ],
          ),
        ),
      ),
    );
  }

  void _updateMarkers(List<TaskModel> tasks) {
    _markers = tasks
        .where((t) => t.hasLocation)
        .map((task) => Marker(
              markerId: MarkerId('task_${task.id}'),
              position: LatLng(task.latitude!, task.longitude!),
              icon: BitmapDescriptor.defaultMarkerWithHue(
                task.isCompleted
                    ? BitmapDescriptor.hueGreen
                    : task.isInProgress
                        ? BitmapDescriptor.hueOrange
                        : task.isFailed
                            ? BitmapDescriptor.hueRed
                            : BitmapDescriptor.hueBlue,
              ),
              infoWindow: InfoWindow(
                title: task.client?.fullName ?? 'Cliente',
                snippet: task.statusLabel,
              ),
            ))
        .toSet();
  }

  void _goToMyLocation() {
    if (_controller != null && _currentPosition != null) {
      _controller!.animateCamera(
        CameraUpdate.newLatLngZoom(_currentPosition!, 15),
      );
    }
  }
}