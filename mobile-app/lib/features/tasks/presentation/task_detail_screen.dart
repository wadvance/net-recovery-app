import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/constants/app_colors.dart';
import '../../../shared/models/task_model.dart';
import '../data/task_repository.dart';

final taskDetailProvider = FutureProvider.family.autoDispose<TaskModel, int>((ref, taskId) async {
  final repository = ref.watch(taskRepositoryProvider);
  return repository.getTask(taskId);
});

class TaskDetailScreen extends ConsumerWidget {
  final int taskId;

  const TaskDetailScreen({super.key, required this.taskId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final taskAsync = ref.watch(taskDetailProvider(taskId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Detalle de Tarea'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.invalidate(taskDetailProvider(taskId)),
          ),
        ],
      ),
      body: taskAsync.when(
        data: (task) => _TaskDetailContent(task: task),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 60.sp, color: AppColors.error),
              SizedBox(height: 16.h),
              Text(error.toString()),
              SizedBox(height: 16.h),
              ElevatedButton(
                onPressed: () => ref.invalidate(taskDetailProvider(taskId)),
                child: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TaskDetailContent extends ConsumerStatefulWidget {
  final TaskModel task;

  const _TaskDetailContent({required this.task});

  @override
  ConsumerState<_TaskDetailContent> createState() => _TaskDetailContentState();
}

class _TaskDetailContentState extends ConsumerState<_TaskDetailContent> {
  @override
  Widget build(BuildContext context) {
    final task = widget.task;

    return Column(
      children: [
        // Action buttons bar
        _buildActionBar(task),
        // Content
        Expanded(
          child: SingleChildScrollView(
            padding: EdgeInsets.all(16.w),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Client info card
                _buildClientInfoCard(task),
                SizedBox(height: 16.h),
                // Task info card
                _buildTaskInfoCard(task),
                SizedBox(height: 16.h),
                // Location card
                if (task.hasLocation || task.client?.address != null)
                  _buildLocationCard(task),
                SizedBox(height: 16.h),
                // Evidence section
                _buildEvidenceSection(task),
                SizedBox(height: 16.h),
                // Comments section
                _buildCommentsSection(task),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildActionBar(TaskModel task) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: const BoxDecoration(
        color: AppColors.white,
        boxShadow: [
          BoxShadow(
            color: AppColors.grey200,
            blurRadius: 10,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          // Navigate button
          Expanded(
            child: ElevatedButton.icon(
              onPressed: task.hasLocation ? () => _navigateToClient(task) : null,
              icon: const Icon(Icons.navigation),
              label: const Text('Navegar'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                padding: EdgeInsets.symmetric(vertical: 12.h),
              ),
            ),
          ),
          SizedBox(width: 12.w),
          // WhatsApp button
          Expanded(
            child: OutlinedButton.icon(
              onPressed: task.client?.phone != null
                  ? () => _openWhatsApp(task)
                  : null,
              icon: const Icon(Icons.chat, color: AppColors.primary),
              label: const Text('WhatsApp'),
              style: OutlinedButton.styleFrom(
                padding: EdgeInsets.symmetric(vertical: 12.h),
              ),
            ),
          ),
          SizedBox(width: 12.w),
          // Call button
          IconButton(
            onPressed: task.client?.phone != null
                ? () => _callClient(task.client!.phone)
                : null,
            icon: const Icon(Icons.phone, color: AppColors.primary),
            style: IconButton.styleFrom(
              backgroundColor: AppColors.primary.withValues(alpha: 0.1),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildClientInfoCard(TaskModel task) {
    final client = task.client;
    if (client == null) return const SizedBox.shrink();

    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.person, color: AppColors.primary, size: 20.sp),
                SizedBox(width: 8.w),
                Text(
                  'Información del Cliente',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
            Divider(height: 24.h),
            _InfoRow(label: 'N° Suscriptor', value: client.metadata?['suscriptor'] ?? '-'),
            _InfoRow(label: 'Nombre', value: client.fullName),
            _InfoRow(label: 'Cedula', value: client.reference ?? client.metadata?['cedula'] ?? '-'),
            _InfoRow(label: 'Tel. Residencia 1', value: client.phone),
            if (client.alternatePhone != null)
              _InfoRow(label: 'Tel. Residencia 2', value: client.alternatePhone!),
            if (client.metadata?['provincia'] != null)
              _InfoRow(label: 'Provincia', value: client.metadata!['provincia'].toString()),
            if (client.metadata?['distrito'] != null)
              _InfoRow(label: 'Distrito', value: client.metadata!['distrito'].toString()),
            if (client.metadata?['corregimiento'] != null)
              _InfoRow(label: 'Corregimiento', value: client.metadata!['corregimiento'].toString()),
            if (client.metadata?['barrio'] != null)
              _InfoRow(label: 'Barrio', value: client.metadata!['barrio'].toString()),
            _InfoRow(label: 'Dirección', value: client.address),
            _InfoRow(label: 'N° Pedido', value: client.orderNumber),
            if (client.company != null)
              _InfoRow(
                label: 'Empresa',
                value: client.company!.name,
                valueColor: AppColors.getCompanyColor(client.company!.code),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildTaskInfoCard(TaskModel task) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.assignment, color: AppColors.primary, size: 20.sp),
                SizedBox(width: 8.w),
                Text(
                  'Información de la Tarea',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
            Divider(height: 24.h),
            _InfoRow(label: 'Título', value: task.title),
            if (task.description != null)
              _InfoRow(label: 'Descripción', value: task.description!),
            _InfoRow(
              label: 'Estado',
              value: task.statusLabel,
              valueColor: AppColors.getStatusColor(task.status),
            ),
            _InfoRow(
              label: 'Prioridad',
              value: task.priorityLabel,
              valueColor: AppColors.getPriorityColor(task.priority),
            ),
            if (task.scheduledDate != null)
              _InfoRow(label: 'Fecha', value: task.scheduledDate!),
            if (task.scheduledTimeStart != null)
              _InfoRow(label: 'Hora', value: '${task.scheduledTimeStart} - ${task.scheduledTimeEnd ?? ""}'),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationCard(TaskModel task) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.location_on, color: AppColors.primary, size: 20.sp),
                SizedBox(width: 8.w),
                Text(
                  'Ubicación',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
            Divider(height: 24.h),
            if (task.hasLocation)
              _InfoRow(
                label: 'Coordenadas',
                value: '${task.latitude}, ${task.longitude}',
              ),
            SizedBox(height: 12.h),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _openGoogleMaps(task),
                    icon: const Icon(Icons.map),
                    label: const Text('Google Maps'),
                  ),
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () => _navigateToClient(task),
                    icon: const Icon(Icons.navigation),
                    label: const Text('Waze'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEvidenceSection(TaskModel task) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.photo_camera, color: AppColors.primary, size: 20.sp),
                SizedBox(width: 8.w),
                Text(
                  'Evidencias',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const Spacer(),
                TextButton.icon(
                  onPressed: () => _addEvidence(task),
                  icon: const Icon(Icons.add_a_photo),
                  label: const Text('Agregar'),
                ),
              ],
            ),
            if (task.evidence != null && task.evidence!.isNotEmpty) ...[
              Divider(height: 24.h),
              SizedBox(
                height: 100.h,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: task.evidence!.length,
                  itemBuilder: (context, index) {
                    final evidence = task.evidence![index];
                    return Container(
                      width: 100.w,
                      margin: EdgeInsets.only(right: 8.w),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(8),
                        color: AppColors.grey200,
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: evidence.type == 'signature'
                            ? Icon(Icons.draw, size: 40.sp, color: AppColors.primary)
                            : Icon(Icons.image, size: 40.sp, color: AppColors.grey500),
                      ),
                    );
                  },
                ),
              ),
            ] else ...[
              SizedBox(height: 12.h),
              Center(
                child: Text(
                  'No hay evidencias aún',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildCommentsSection(TaskModel task) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.comment, color: AppColors.primary, size: 20.sp),
                SizedBox(width: 8.w),
                Text(
                  'Comentarios',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
            if (task.comments != null && task.comments!.isNotEmpty) ...[
              Divider(height: 24.h),
              ...task.comments!.map((comment) => Padding(
                    padding: EdgeInsets.only(bottom: 12.h),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              comment.user?.name ?? 'Usuario',
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            const Spacer(),
                            Text(
                              comment.createdAt ?? '',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                        SizedBox(height: 4.h),
                        Text(
                          comment.comment,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  )),
            ],
            SizedBox(height: 12.h),
            OutlinedButton.icon(
              onPressed: () => _addComment(task),
              icon: const Icon(Icons.add),
              label: const Text('Agregar comentario'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _navigateToClient(TaskModel task) async {
    final uri = Uri.parse(task.wazeUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _openGoogleMaps(TaskModel task) async {
    final url = task.googleMapsUrl;
    final Uri uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _openWhatsApp(TaskModel task) async {
    if (task.client?.phone == null) return;
    final url = task.client!.whatsappUrl;
    final Uri uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _callClient(String phone) async {
    final Uri uri = Uri.parse('tel:$phone');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _addEvidence(TaskModel task) async {
    // TODO: Implement camera/gallery picker
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Tomar foto'),
              onTap: () {
                Navigator.pop(context);
                // TODO: Open camera
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Elegir de galería'),
              onTap: () {
                Navigator.pop(context);
                // TODO: Open gallery
              },
            ),
            ListTile(
              leading: const Icon(Icons.draw),
              title: const Text('Firma del cliente'),
              onTap: () {
                Navigator.pop(context);
                // TODO: Open signature pad
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _addComment(TaskModel task) async {
    final controller = TextEditingController();
    final comment = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Agregar comentario'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Escribe tu comentario...',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );

    if (comment != null && comment.isNotEmpty) {
      try {
        final repository = ref.read(taskRepositoryProvider);
        await repository.addComment(task.id, comment);
        ref.invalidate(taskDetailProvider(task.id));
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
          );
        }
      }
    }
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;

  const _InfoRow({
    required this.label,
    required this.value,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: 8.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100.w,
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.grey600,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: valueColor,
                fontWeight: valueColor != null ? FontWeight.w500 : null,
              ),
            ),
          ),
        ],
      ),
    );
  }
}