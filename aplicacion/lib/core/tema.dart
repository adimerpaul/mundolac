import 'package:flutter/material.dart';

/// Paleta de la aplicación: verde de punto de venta sobre fondo claro.
class AppColores {
  static const verde = Color(0xFF12866B);
  static const verdeOscuro = Color(0xFF0B6A54);
  static const verdeSuave = Color(0xFFE6F4EF);
  static const fondo = Color(0xFFF4F6F8);
  static const texto = Color(0xFF1B2733);
  static const textoSuave = Color(0xFF7A8894);
  static const borde = Color(0xFFE3E8EC);
  static const rojo = Color(0xFFD64545);
  static const ambar = Color(0xFFE8A33D);
}

ThemeData construirTema() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColores.verde,
      primary: AppColores.verde,
      brightness: Brightness.light,
    ),
    scaffoldBackgroundColor: AppColores.fondo,
  );

  return base.copyWith(
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: AppColores.texto,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        color: AppColores.texto,
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColores.borde),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColores.borde),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColores.verde, width: 1.6),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColores.verde,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(vertical: 16),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: AppColores.verde),
    ),
    dividerTheme: const DividerThemeData(color: AppColores.borde, space: 1, thickness: 1),
  );
}
