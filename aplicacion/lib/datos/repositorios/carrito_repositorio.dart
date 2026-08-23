import 'package:sqflite/sqflite.dart';

import '../../modelos/item_carrito.dart';
import '../servicios/base_datos.dart';

/// El carrito vive en SQLite: si se cierra la app, la venta en curso no se pierde.
class CarritoRepositorio {
  Future<List<ItemCarrito>> listar() async {
    final db = await BaseDatos.instancia;
    final filas = await db.query('carrito', orderBy: 'agregado');
    return filas.map((f) => ItemCarrito.desdeFila(f)).toList();
  }

  Future<void> guardar(ItemCarrito item) async {
    final db = await BaseDatos.instancia;
    await db.insert('carrito', item.aFila(), conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<void> actualizarCantidad(int productoId, int cantidad) async {
    final db = await BaseDatos.instancia;
    await db.update(
      'carrito',
      {'cantidad': cantidad},
      where: 'producto_id = ?',
      whereArgs: [productoId],
    );
  }

  Future<void> quitar(int productoId) async {
    final db = await BaseDatos.instancia;
    await db.delete('carrito', where: 'producto_id = ?', whereArgs: [productoId]);
  }

  Future<void> limpiar() async {
    final db = await BaseDatos.instancia;
    await db.delete('carrito');
  }
}
