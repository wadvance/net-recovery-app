// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'task_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TaskModel _$TaskModelFromJson(Map<String, dynamic> json) => TaskModel(
      id: (json['id'] as num).toInt(),
      companyId: (json['company_id'] as num).toInt(),
      clientId: (json['client_id'] as num).toInt(),
      assignedTo: (json['assigned_to'] as num?)?.toInt(),
      title: json['title'] as String,
      description: json['description'] as String?,
      type: json['type'] as String? ?? 'recovery',
      priority: json['priority'] as String? ?? 'normal',
      status: json['status'] as String? ?? 'pending',
      scheduledDate: json['scheduled_date'] as String?,
      scheduledTimeStart: json['scheduled_time_start'] as String?,
      scheduledTimeEnd: json['scheduled_time_end'] as String?,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      address: json['address'] as String?,
      checklist: json['checklist'] as List<dynamic>?,
      metadata: json['metadata'] as Map<String, dynamic>?,
      startedAt: json['started_at'] as String?,
      completedAt: json['completed_at'] as String?,
      createdAt: json['created_at'] as String?,
      client: json['client'] == null
          ? null
          : ClientModel.fromJson(json['client'] as Map<String, dynamic>),
      assignee: json['assignee'] == null
          ? null
          : UserModel.fromJson(json['assignee'] as Map<String, dynamic>),
      evidence: (json['evidence'] as List<dynamic>?)
          ?.map((e) => TaskEvidenceModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      comments: (json['comments'] as List<dynamic>?)
          ?.map((e) => TaskCommentModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$TaskModelToJson(TaskModel instance) => <String, dynamic>{
      'id': instance.id,
      'company_id': instance.companyId,
      'client_id': instance.clientId,
      'assigned_to': instance.assignedTo,
      'title': instance.title,
      'description': instance.description,
      'type': instance.type,
      'priority': instance.priority,
      'status': instance.status,
      'scheduled_date': instance.scheduledDate,
      'scheduled_time_start': instance.scheduledTimeStart,
      'scheduled_time_end': instance.scheduledTimeEnd,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'address': instance.address,
      'checklist': instance.checklist,
      'metadata': instance.metadata,
      'started_at': instance.startedAt,
      'completed_at': instance.completedAt,
      'created_at': instance.createdAt,
      'client': instance.client,
      'assignee': instance.assignee,
      'evidence': instance.evidence,
      'comments': instance.comments,
    };

ClientModel _$ClientModelFromJson(Map<String, dynamic> json) => ClientModel(
      id: (json['id'] as num).toInt(),
      companyId: (json['company_id'] as num).toInt(),
      orderNumber: json['order_number'] as String,
      fullName: json['full_name'] as String,
      phone: json['phone'] as String,
      alternatePhone: json['alternate_phone'] as String?,
      address: json['address'] as String,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      reference: json['reference'] as String?,
      equipmentDetails: json['equipment_details'] as Map<String, dynamic>?,
      status: json['status'] as String? ?? 'pending',
      metadata: json['metadata'] as Map<String, dynamic>?,
      createdAt: json['created_at'] as String?,
      company: json['company'] == null
          ? null
          : CompanyModel.fromJson(json['company'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$ClientModelToJson(ClientModel instance) =>
    <String, dynamic>{
      'id': instance.id,
      'company_id': instance.companyId,
      'order_number': instance.orderNumber,
      'full_name': instance.fullName,
      'phone': instance.phone,
      'alternate_phone': instance.alternatePhone,
      'address': instance.address,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'reference': instance.reference,
      'equipment_details': instance.equipmentDetails,
      'status': instance.status,
      'metadata': instance.metadata,
      'created_at': instance.createdAt,
      'company': instance.company,
    };

CompanyModel _$CompanyModelFromJson(Map<String, dynamic> json) => CompanyModel(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      code: json['code'] as String,
      logo: json['logo'] as String?,
      description: json['description'] as String?,
      settings: json['settings'] as Map<String, dynamic>?,
      isActive: json['is_active'] as bool? ?? true,
    );

Map<String, dynamic> _$CompanyModelToJson(CompanyModel instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'code': instance.code,
      'logo': instance.logo,
      'description': instance.description,
      'settings': instance.settings,
      'is_active': instance.isActive,
    };

TaskEvidenceModel _$TaskEvidenceModelFromJson(Map<String, dynamic> json) =>
    TaskEvidenceModel(
      id: (json['id'] as num).toInt(),
      taskId: (json['task_id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      type: json['type'] as String,
      filePath: json['file_path'] as String,
      description: json['description'] as String?,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      createdAt: json['created_at'] as String?,
    );

Map<String, dynamic> _$TaskEvidenceModelToJson(TaskEvidenceModel instance) =>
    <String, dynamic>{
      'id': instance.id,
      'task_id': instance.taskId,
      'user_id': instance.userId,
      'type': instance.type,
      'file_path': instance.filePath,
      'description': instance.description,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'created_at': instance.createdAt,
    };

TaskCommentModel _$TaskCommentModelFromJson(Map<String, dynamic> json) =>
    TaskCommentModel(
      id: (json['id'] as num).toInt(),
      taskId: (json['task_id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      comment: json['comment'] as String,
      isInternal: json['is_internal'] as bool? ?? false,
      createdAt: json['created_at'] as String?,
      user: json['user'] == null
          ? null
          : UserModel.fromJson(json['user'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$TaskCommentModelToJson(TaskCommentModel instance) =>
    <String, dynamic>{
      'id': instance.id,
      'task_id': instance.taskId,
      'user_id': instance.userId,
      'comment': instance.comment,
      'is_internal': instance.isInternal,
      'created_at': instance.createdAt,
      'user': instance.user,
    };
