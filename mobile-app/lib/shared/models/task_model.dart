import 'package:json_annotation/json_annotation.dart';
import 'user_model.dart';

part 'task_model.g.dart';

@JsonSerializable()
class TaskModel {
  final int id;
  @JsonKey(name: 'company_id')
  final int companyId;
  @JsonKey(name: 'client_id')
  final int clientId;
  @JsonKey(name: 'assigned_to')
  final int? assignedTo;
  final String title;
  final String? description;
  final String type;
  final String priority;
  final String status;
  @JsonKey(name: 'scheduled_date')
  final String? scheduledDate;
  @JsonKey(name: 'scheduled_time_start')
  final String? scheduledTimeStart;
  @JsonKey(name: 'scheduled_time_end')
  final String? scheduledTimeEnd;
  final double? latitude;
  final double? longitude;
  final String? address;
  final List<dynamic>? checklist;
  final Map<String, dynamic>? metadata;
  @JsonKey(name: 'started_at')
  final String? startedAt;
  @JsonKey(name: 'completed_at')
  final String? completedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  final ClientModel? client;
  final UserModel? assignee;
  final List<TaskEvidenceModel>? evidence;
  final List<TaskCommentModel>? comments;

  TaskModel({
    required this.id,
    required this.companyId,
    required this.clientId,
    this.assignedTo,
    required this.title,
    this.description,
    this.type = 'recovery',
    this.priority = 'normal',
    this.status = 'pending',
    this.scheduledDate,
    this.scheduledTimeStart,
    this.scheduledTimeEnd,
    this.latitude,
    this.longitude,
    this.address,
    this.checklist,
    this.metadata,
    this.startedAt,
    this.completedAt,
    this.createdAt,
    this.client,
    this.assignee,
    this.evidence,
    this.comments,
  });

  factory TaskModel.fromJson(Map<String, dynamic> json) => _$TaskModelFromJson(json);
  Map<String, dynamic> toJson() => _$TaskModelToJson(this);

  bool get isPending => status == 'pending';
  bool get isAssigned => status == 'assigned';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed';
  bool get isFailed => status == 'failed';
  bool get hasLocation => latitude != null && longitude != null;

  String get statusLabel {
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

  String get priorityLabel {
    return switch (priority) {
      'low' => 'Baja',
      'normal' => 'Normal',
      'high' => 'Alta',
      'urgent' => 'Urgente',
      _ => priority,
    };
  }

  String get googleMapsUrl {
    if (latitude != null && longitude != null) {
      return 'https://www.google.com/maps/dir/?api=1&destination=$latitude,$longitude';
    }
    return 'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(address ?? client?.address ?? '')}';
  }

  String get wazeUrl {
    if (latitude != null && longitude != null) {
      return 'https://waze.com/ul?ll=$latitude,$longitude&navigate=yes';
    }
    return 'https://waze.com/ul?q=${Uri.encodeComponent(address ?? client?.address ?? client?.fullName ?? '')}&navigate=yes';
  }

  String get navigationUrl {
    if (latitude != null && longitude != null) {
      return 'google.navigation:q=$latitude,$longitude&mode=d';
    }
    return 'google.navigation:q=${Uri.encodeComponent(address ?? client?.address ?? '')}&mode=d';
  }
}

@JsonSerializable()
class ClientModel {
  final int id;
  @JsonKey(name: 'company_id')
  final int companyId;
  @JsonKey(name: 'order_number')
  final String orderNumber;
  @JsonKey(name: 'full_name')
  final String fullName;
  final String phone;
  @JsonKey(name: 'alternate_phone')
  final String? alternatePhone;
  final String address;
  final double? latitude;
  final double? longitude;
  final String? reference;
  @JsonKey(name: 'equipment_details')
  final Map<String, dynamic>? equipmentDetails;
  final String status;
  final Map<String, dynamic>? metadata;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  final CompanyModel? company;

  ClientModel({
    required this.id,
    required this.companyId,
    required this.orderNumber,
    required this.fullName,
    required this.phone,
    this.alternatePhone,
    required this.address,
    this.latitude,
    this.longitude,
    this.reference,
    this.equipmentDetails,
    this.status = 'pending',
    this.metadata,
    this.createdAt,
    this.company,
  });

  factory ClientModel.fromJson(Map<String, dynamic> json) => _$ClientModelFromJson(json);
  Map<String, dynamic> toJson() => _$ClientModelToJson(this);

  String get formattedPhone {
    String p = phone.replaceAll(RegExp(r'\D'), '');
    if (p.startsWith('507')) return p;
    return '507${p.replaceFirst(RegExp(r'^0'), '')}';
  }

  String get whatsappUrl => 'https://wa.me/$formattedPhone';
}

@JsonSerializable()
class CompanyModel {
  final int id;
  final String name;
  final String code;
  final String? logo;
  final String? description;
  final Map<String, dynamic>? settings;
  @JsonKey(name: 'is_active')
  final bool isActive;

  CompanyModel({
    required this.id,
    required this.name,
    required this.code,
    this.logo,
    this.description,
    this.settings,
    this.isActive = true,
  });

  factory CompanyModel.fromJson(Map<String, dynamic> json) => _$CompanyModelFromJson(json);
  Map<String, dynamic> toJson() => _$CompanyModelToJson(this);
}

@JsonSerializable()
class TaskEvidenceModel {
  final int id;
  @JsonKey(name: 'task_id')
  final int taskId;
  @JsonKey(name: 'user_id')
  final int userId;
  final String type;
  @JsonKey(name: 'file_path')
  final String filePath;
  final String? description;
  final double? latitude;
  final double? longitude;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  TaskEvidenceModel({
    required this.id,
    required this.taskId,
    required this.userId,
    required this.type,
    required this.filePath,
    this.description,
    this.latitude,
    this.longitude,
    this.createdAt,
  });

  factory TaskEvidenceModel.fromJson(Map<String, dynamic> json) => _$TaskEvidenceModelFromJson(json);
  Map<String, dynamic> toJson() => _$TaskEvidenceModelToJson(this);

  String get fullUrl => 'http://10.0.2.2:8000/storage/$filePath';
}

@JsonSerializable()
class TaskCommentModel {
  final int id;
  @JsonKey(name: 'task_id')
  final int taskId;
  @JsonKey(name: 'user_id')
  final int userId;
  final String comment;
  @JsonKey(name: 'is_internal')
  final bool isInternal;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  final UserModel? user;

  TaskCommentModel({
    required this.id,
    required this.taskId,
    required this.userId,
    required this.comment,
    this.isInternal = false,
    this.createdAt,
    this.user,
  });

  factory TaskCommentModel.fromJson(Map<String, dynamic> json) => _$TaskCommentModelFromJson(json);
  Map<String, dynamic> toJson() => _$TaskCommentModelToJson(this);
}