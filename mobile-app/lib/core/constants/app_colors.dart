import 'package:flutter/material.dart';

class AppColors {
  // Brand Colors
  static const Color primary = Color(0xFF00A3E0);
  static const Color primaryDark = Color(0xFF0077B6);
  static const Color primaryLight = Color(0xFFB3E5FC);

  // Company Colors
  static const Color tigo = Color(0xFF00A3E0);
  static const Color masmovil = Color(0xFFFF6B00);
  static const Color telca = Color(0xFF0066CC);

  // Status Colors
  static const Color pending = Color(0xFFFFA726);
  static const Color assigned = Color(0xFF42A5F5);
  static const Color inProgress = Color(0xFF7E57C2);
  static const Color completed = Color(0xFF66BB6A);
  static const Color failed = Color(0xFFEF5350);
  static const Color cancelled = Color(0xFFBDBDBD);
  static const Color rescheduled = Color(0xFFFFCA28);

  // Priority Colors
  static const Color low = Color(0xFF81C784);
  static const Color normal = Color(0xFF64B5F6);
  static const Color high = Color(0xFFFFB74D);
  static const Color urgent = Color(0xFFE57373);

  // Neutral Colors
  static const Color white = Color(0xFFFFFFFF);
  static const Color black = Color(0xFF000000);
  static const Color grey50 = Color(0xFFFAFAFA);
  static const Color grey100 = Color(0xFFF5F5F5);
  static const Color grey200 = Color(0xFFEEEEEE);
  static const Color grey300 = Color(0xFFE0E0E0);
  static const Color grey400 = Color(0xFFBDBDBD);
  static const Color grey500 = Color(0xFF9E9E9E);
  static const Color grey600 = Color(0xFF757575);
  static const Color grey700 = Color(0xFF616161);
  static const Color grey800 = Color(0xFF424242);
  static const Color grey900 = Color(0xFF212121);

  // Background
  static const Color background = Color(0xFFF8F9FA);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color error = Color(0xFFD32F2F);

  // Text
  static const Color textPrimary = Color(0xFF212121);
  static const Color textSecondary = Color(0xFF757575);
  static const Color textHint = Color(0xFFBDBDBD);

  static Color getStatusColor(String status) {
    return switch (status) {
      'pending' => pending,
      'assigned' => assigned,
      'in_progress' => inProgress,
      'completed' => completed,
      'failed' => failed,
      'cancelled' => cancelled,
      'rescheduled' => rescheduled,
      _ => grey400,
    };
  }

  static Color getPriorityColor(String priority) {
    return switch (priority) {
      'low' => low,
      'normal' => normal,
      'high' => high,
      'urgent' => urgent,
      _ => normal,
    };
  }

  static Color getCompanyColor(String code) {
    return switch (code.toUpperCase()) {
      'TIGO' => tigo,
      'MASMOVIL' => masmovil,
      'TELCA' => telca,
      _ => primary,
    };
  }
}