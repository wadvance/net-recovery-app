import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';

import '../../../core/constants/app_colors.dart';
import '../../../shared/models/task_model.dart';
import '../data/task_repository.dart';

final tasksProvider = FutureProvider.autoDispose<List<TaskModel>>((ref) async {
  final repository = ref.watch(taskRepositoryProvider);
  return repository.getMyTasks();
});

final tasksByDateProvider = FutureProvider.family.autoDispose<List<TaskModel>, String>((ref, date) async {
  final repository = ref.watch(taskRepositoryProvider);
  return repository.getMyTasksByDate(date);
});

class TasksScreen extends ConsumerStatefulWidget {
  const TasksScreen({super.key});

  @override
  ConsumerState<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends ConsumerState<TasksScreen> {
  String _selectedDate = DateTime.now().toIso8601String().split('T')[0];
  String? _selectedStatus;

  @override
  Widget build(BuildContext context) {
    final tasksAsync = ref.watch(tasksByDateProvider(_selectedDate));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mis Tareas'),
        actions: [
          IconButton(
            icon: const Icon(Icons.calendar_today),
            onPressed: _selectDate,
          ),
          PopupMenuButton<String?>(
            icon: const Icon(Icons.filter_list),
            onSelected: (value) {
              setState(() {
                _selectedStatus = value;
              });
            },
            itemBuilder: (context) => [
              const PopupMenuItem(value: null, child: Text('Todas')),
              const PopupMenuItem(value: 'assigned', child: Text('Asignadas')),
              const PopupMenuItem(value: 'in_progress', child: Text('En Progreso')),
              const PopupMenuItem(value: 'completed', child: Text('Completadas')),
              const PopupMenuItem(value: 'failed', child: Text('Fallidas')),
            ],
          ),
        ],
      ),
      body: Column(
        children: [
          // Date selector
          Container(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
            child: Row(
              children: [
                Icon(Icons.calendar_today, size: 16.sp, color: AppColors.grey600),
                SizedBox(width: 8.w),
                Text(
                  _formatDateLabel(_selectedDate),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const Spacer(),
                TextButton(
                  onPressed: _selectDate,
                  child: const Text('Cambiar fecha'),
                ),
              ],
            ),
          ),
          // Stats summary
          _buildStatsBar(),
          // Tasks list
          Expanded(
            child: tasksAsync.when(
              data: (tasks) {
                var filteredTasks = tasks;
                if (_selectedStatus != null) {
                  filteredTasks = tasks.where((t) => t.status == _selectedStatus).toList();
                }

                if (filteredTasks.isEmpty) {
                  return _buildEmptyState();
                }

                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(tasksByDateProvider(_selectedDate));
                  },
                  child: ListView.builder(
                    padding: EdgeInsets.all(16.w),
                    itemCount: filteredTasks.length,
                    itemBuilder: (context, index) {
                      return _TaskCard(task: filteredTasks[index]);
                    },
                  ),
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, stack) => _buildErrorState(error.toString()),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsBar() {
    final tasksAsync = ref.watch(tasksByDateProvider(_selectedDate));

    return tasksAsync.when(
      data: (tasks) {
        final total = tasks.length;
        final completed = tasks.where((t) => t.isCompleted).length;
        final inProgress = tasks.where((t) => t.isInProgress).length;
        final pending = tasks.where((t) => t.isAssigned).length;

        return Container(
          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
          margin: EdgeInsets.symmetric(horizontal: 16.w),
          decoration: BoxDecoration(
            color: AppColors.primary.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _StatItem(label: 'Total', value: total, color: AppColors.primary),
              _StatItem(label: 'Pendientes', value: pending, color: AppColors.assigned),
              _StatItem(label: 'En Progreso', value: inProgress, color: AppColors.inProgress),
              _StatItem(label: 'Completadas', value: completed, color: AppColors.completed),
            ],
          ),
        );
      },
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.assignment_outlined,
            size: 80.sp,
            color: AppColors.grey400,
          ),
          SizedBox(height: 16.h),
          Text(
            'No hay tareas para esta fecha',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: AppColors.grey600,
            ),
          ),
          SizedBox(height: 8.h),
          Text(
            'Selecciona otra fecha o espera asignaciones',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState(String error) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(24.w),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 60.sp, color: AppColors.error),
            SizedBox(height: 16.h),
            Text(
              'Error cargando tareas',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            SizedBox(height: 8.h),
            Text(
              error,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 16.h),
            ElevatedButton(
              onPressed: () => ref.invalidate(tasksByDateProvider(_selectedDate)),
              child: const Text('Reintentar'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.parse(_selectedDate),
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );

    if (picked != null) {
      setState(() {
        _selectedDate = picked.toIso8601String().split('T')[0];
      });
    }
  }

  String _formatDateLabel(String dateStr) {
    final date = DateTime.parse(dateStr);
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final tomorrow = today.add(const Duration(days: 1));
    final target = DateTime(date.year, date.month, date.day);

    if (target == today) return 'Hoy';
    if (target == yesterday) return 'Ayer';
    if (target == tomorrow) return 'Mañana';

    final months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return '${date.day} ${months[date.month - 1]}';
  }
}

class _StatItem extends StatelessWidget {
  final String label;
  final int value;
  final Color color;

  const _StatItem({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          '$value',
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            color: color,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }
}

class _TaskCard extends StatelessWidget {
  final TaskModel task;

  const _TaskCard({required this.task});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.only(bottom: 12.h),
      child: InkWell(
        onTap: () => context.go('/tasks/${task.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: EdgeInsets.all(16.w),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 4.w,
                    height: 40.h,
                    decoration: BoxDecoration(
                      color: AppColors.getStatusColor(task.status),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          task.client?.fullName ?? 'Cliente sin nombre',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        SizedBox(height: 4.h),
                        Text(
                          'N° Pedido #${task.client?.orderNumber ?? ''}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        if (task.client?.metadata?['suscriptor'] != null) ...[
                          SizedBox(height: 2.h),
                          Text(
                            'N° Suscriptor: ${task.client!.metadata!['suscriptor']}',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.grey500,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  _StatusChip(status: task.status),
                ],
              ),
              SizedBox(height: 12.h),
              Row(
                children: [
                  Icon(Icons.phone, size: 14.sp, color: AppColors.grey500),
                  SizedBox(width: 4.w),
                  Text(
                    task.client?.phone ?? '',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  SizedBox(width: 16.w),
                  Icon(Icons.location_on_outlined, size: 14.sp, color: AppColors.grey500),
                  SizedBox(width: 4.w),
                  Expanded(
                    child: Text(
                      task.client?.address ?? '',
                      style: Theme.of(context).textTheme.bodySmall,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
              if (task.client?.company != null) ...[
                SizedBox(height: 8.h),
                Row(
                  children: [
                    Container(
                      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 2.h),
                      decoration: BoxDecoration(
                        color: AppColors.getCompanyColor(task.client!.company!.code).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        task.client!.company!.name,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.getCompanyColor(task.client!.company!.code),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    const Spacer(),
                    if (task.hasLocation)
                      Icon(Icons.navigation_outlined, size: 16.sp, color: AppColors.primary),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;

  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final color = AppColors.getStatusColor(status);
    final label = _getStatusLabel(status);

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 4.h),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: color,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }

  String _getStatusLabel(String status) {
    return switch (status) {
      'pending' => 'Pendiente',
      'assigned' => 'Asignado',
      'in_progress' => 'En Progreso',
      'completed' => 'Completado',
      'failed' => 'Fallido',
      'cancelled' => 'Cancelado',
      'rescheduled' => 'Reagendado',
      _ => status,
    };
  }
}