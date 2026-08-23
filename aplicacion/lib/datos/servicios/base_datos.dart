import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Base de datos local (SQLite) donde se guardan la sesión, los ajustes,
/// el catálogo de productos, el carrito en curso y las últimas ventas.
class BaseDatos {
  static Database? _db;

  static Future<Database> get instancia async {
    if (_db != null) return _db!;
    final ruta = p.join(await getDatabasesPath(), 'mundolac.db');
    _db = await openDatabase(
      ruta,
      version: 1,
      onConfigure: (db) => db.execute('PRAGMA foreign_keys = ON'),
      onCreate: (db, version) async {
        final lote = db.batch();

        lote.execute('''
          CREATE TABLE ajustes (
            clave TEXT PRIMARY KEY,
            valor TEXT
          )
        ''');

        lote.execute('''
          CREATE TABLE sesion (
            id INTEGER PRIMARY KEY,
            token TEXT NOT NULL,
            user_id INTEGER,
            nombre TEXT,
            username TEXT,
            permisos TEXT,
            actualizado TEXT
          )
        ''');

        lote.execute('''
          CREATE TABLE productos (
            id INTEGER PRIMARY KEY,
            codigo TEXT,
            codigo_barras TEXT,
            nombre TEXT,
            categoria TEXT,
            unidad TEXT,
            precio_compra REAL,
            precio_venta REAL,
            stock INTEGER,
            foto TEXT
          )
        ''');
        lote.execute('CREATE INDEX idx_productos_nombre ON productos (nombre)');
        lote.execute('CREATE INDEX idx_productos_barras ON productos (codigo_barras)');

        lote.execute('''
          CREATE TABLE carrito (
            producto_id INTEGER PRIMARY KEY,
            codigo TEXT,
            nombre TEXT,
            unidad TEXT,
            foto TEXT,
            precio_venta REAL,
            stock INTEGER,
            cantidad INTEGER,
            agregado TEXT
          )
        ''');

        lote.execute('''
          CREATE TABLE ventas (
            id INTEGER PRIMARY KEY,
            numero TEXT,
            fecha TEXT,
            subtotal REAL,
            descuento REAL,
            total REAL,
            tipo_pago TEXT,
            monto_efectivo REAL,
            monto_qr REAL,
            estado TEXT,
            usuario_nombre TEXT,
            detalles TEXT
          )
        ''');
        lote.execute('CREATE INDEX idx_ventas_fecha ON ventas (fecha)');

        await lote.commit(noResult: true);
      },
    );
    return _db!;
  }

  // ── Ajustes ──────────────────────────────────────────────

  static Future<String?> ajuste(String clave) async {
    final db = await instancia;
    final filas = await db.query('ajustes', where: 'clave = ?', whereArgs: [clave], limit: 1);
    return filas.isEmpty ? null : filas.first['valor'] as String?;
  }

  static Future<void> guardarAjuste(String clave, String valor) async {
    final db = await instancia;
    await db.insert(
      'ajustes',
      {'clave': clave, 'valor': valor},
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }
}
