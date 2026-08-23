import 'dart:async';

import '../datos/repositorios/carrito_repositorio.dart';
import '../datos/repositorios/producto_repositorio.dart';
import '../modelos/item_carrito.dart';
import '../modelos/producto.dart';
import 'vista_modelo_base.dart';

/// ViewModel del punto de venta: carrito en curso y buscador de productos.
class VentaVistaModelo extends VistaModeloBase {
  VentaVistaModelo(this._carritos, this._productos);

  final CarritoRepositorio _carritos;
  final ProductoRepositorio _productos;

  final List<ItemCarrito> _items = [];
  List<Producto> _resultados = [];
  String _busqueda = '';
  String? _aviso;
  bool _escaneando = false;
  Timer? _rebote;

  List<ItemCarrito> get items => List.unmodifiable(_items);
  List<Producto> get resultados => List.unmodifiable(_resultados);
  String get busqueda => _busqueda;
  String? get aviso => _aviso;
  bool get escaneando => _escaneando;
  bool get buscando => _busqueda.trim().isNotEmpty;
  bool get vacio => _items.isEmpty;

  int get cantidadItems => _items.fold(0, (suma, item) => suma + item.cantidad);
  int get lineas => _items.length;
  double get subtotal => _items.fold(0.0, (suma, item) => suma + item.total);

  Future<void> iniciar() async {
    await ejecutar(() async {
      _items
        ..clear()
        ..addAll(await _carritos.listar());
      _resultados = await _productos.buscarLocal('');
    });
  }

  void alternarEscaner() {
    _escaneando = !_escaneando;
    notificar();
  }

  void cerrarEscaner() {
    if (!_escaneando) return;
    _escaneando = false;
    notificar();
  }

  /// Búsqueda con rebote para no consultar SQLite en cada tecla.
  void buscar(String texto) {
    _busqueda = texto;
    notificar();
    _rebote?.cancel();
    _rebote = Timer(const Duration(milliseconds: 220), () async {
      _resultados = await _productos.buscarLocal(texto);
      notificar();
    });
  }

  void limpiarBusqueda() {
    _rebote?.cancel();
    _busqueda = '';
    buscar('');
  }

  Future<void> agregar(Producto producto, {int cantidad = 1}) async {
    final indice = _items.indexWhere((item) => item.productoId == producto.id);
    final actual = indice >= 0 ? _items[indice].cantidad : 0;
    final deseada = actual + cantidad;

    if (producto.stock <= 0) {
      _mostrarAviso('${producto.nombre} no tiene stock disponible');
      return;
    }
    if (deseada > producto.stock) {
      _mostrarAviso('Sólo hay ${producto.stock} ${producto.unidad} de ${producto.nombre}');
      return;
    }

    final item = indice >= 0
        ? _items[indice].copiar(cantidad: deseada)
        : ItemCarrito.desdeProducto(producto, cantidad: cantidad);

    if (indice >= 0) {
      _items[indice] = item;
    } else {
      _items.add(item);
    }
    notificar();
    await _carritos.guardar(item);
  }

  Future<void> cambiarCantidad(ItemCarrito item, int cantidad) async {
    if (cantidad <= 0) return quitar(item);
    if (cantidad > item.stock) {
      _mostrarAviso('Sólo hay ${item.stock} ${item.unidad} de ${item.nombre}');
      return;
    }
    final indice = _items.indexWhere((i) => i.productoId == item.productoId);
    if (indice < 0) return;
    _items[indice] = item.copiar(cantidad: cantidad);
    notificar();
    await _carritos.actualizarCantidad(item.productoId, cantidad);
  }

  Future<void> aumentar(ItemCarrito item) => cambiarCantidad(item, item.cantidad + 1);

  Future<void> disminuir(ItemCarrito item) => cambiarCantidad(item, item.cantidad - 1);

  Future<void> quitar(ItemCarrito item) async {
    _items.removeWhere((i) => i.productoId == item.productoId);
    notificar();
    await _carritos.quitar(item.productoId);
  }

  Future<void> limpiar() async {
    _items.clear();
    notificar();
    await _carritos.limpiar();
  }

  /// Agrega el producto leído por el escáner. Devuelve `true` si lo encontró.
  Future<bool> agregarPorCodigo(String codigo) async {
    final producto = await _productos.porCodigoBarras(codigo);
    if (producto == null) {
      _mostrarAviso('Código $codigo no registrado');
      return false;
    }
    await agregar(producto);
    if (_aviso == null) _mostrarAviso('${producto.nombre} agregado');
    return true;
  }

  /// Tras registrar una venta el carrito ya se vació en la base local.
  Future<void> recargarDespuesDeVenta() async {
    _items
      ..clear()
      ..addAll(await _carritos.listar());
    _resultados = await _productos.buscarLocal(_busqueda);
    notificar();
  }

  void _mostrarAviso(String mensaje) {
    _aviso = mensaje;
    notificar();
  }

  void limpiarAviso() {
    if (_aviso == null) return;
    _aviso = null;
    notificar();
  }

  @override
  void dispose() {
    _rebote?.cancel();
    super.dispose();
  }
}
