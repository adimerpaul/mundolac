import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../modelos/item_carrito.dart';
import '../../modelos/producto.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../../vistamodelos/venta_vistamodelo.dart';
import '../widgets/comunes.dart';
import '../widgets/foto_producto.dart';
import 'cobro_vista.dart';
import 'escaner_camara.dart';

/// Punto de venta: buscador + escáner arriba, carrito abajo.
class VentaVista extends StatefulWidget {
  const VentaVista({super.key});

  @override
  State<VentaVista> createState() => _VentaVistaState();
}

class _VentaVistaState extends State<VentaVista> {
  final _busqueda = TextEditingController();

  @override
  void dispose() {
    _busqueda.dispose();
    super.dispose();
  }

  void _escucharAvisos(VentaVistaModelo vm) {
    final aviso = vm.aviso;
    if (aviso == null) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      mostrarMensaje(context, aviso);
      vm.limpiarAviso();
    });
  }

  Future<void> _revisarOrden() async {
    final vm = context.read<VentaVistaModelo>();
    if (vm.vacio) {
      mostrarMensaje(context, 'Agregue productos antes de cobrar', esError: true);
      return;
    }
    vm.cerrarEscaner();

    final registrada = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => CobroVista(items: vm.items)),
    );

    if (registrada == true) {
      await vm.recargarDespuesDeVenta();
      _busqueda.clear();
      vm.limpiarBusqueda();
    }
  }

  Future<void> _confirmarLimpiar() async {
    final vm = context.read<VentaVistaModelo>();
    if (vm.vacio) return;

    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Limpiar carrito'),
        content: const Text('¿Quitar todos los productos de esta venta?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto, false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.pop(contexto, true), child: const Text('Sí, limpiar')),
        ],
      ),
    );

    if (confirmado == true) await vm.limpiar();
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<VentaVistaModelo>();
    final sesion = context.watch<SesionVistaModelo>();
    _escucharAvisos(vm);

    if (!sesion.puedeVender) {
      return const _SinPermiso(mensaje: 'No tiene el permiso "Crear Ventas".');
    }

    return Column(
      children: [
        _Buscador(
          controlador: _busqueda,
          escaneando: vm.escaneando,
          alBuscar: vm.buscar,
          alLimpiar: () {
            _busqueda.clear();
            vm.limpiarBusqueda();
          },
          alEscanear: vm.alternarEscaner,
        ),
        if (vm.escaneando)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
            child: SizedBox(
              height: MediaQuery.of(context).size.height * 0.30,
              child: EscanerCamara(
                alLeer: vm.agregarPorCodigo,
                alCerrar: vm.cerrarEscaner,
              ),
            ),
          ),
        Expanded(
          child: vm.buscando
              ? _ResultadosBusqueda(
                  resultados: vm.resultados,
                  alAgregar: vm.agregar,
                )
              : _Carrito(
                  vm: vm,
                  alLimpiar: _confirmarLimpiar,
                ),
        ),
        _BarraCobro(
          total: vm.subtotal,
          items: vm.cantidadItems,
          habilitado: !vm.vacio,
          alRevisar: _revisarOrden,
        ),
      ],
    );
  }
}

class _Buscador extends StatelessWidget {
  const _Buscador({
    required this.controlador,
    required this.escaneando,
    required this.alBuscar,
    required this.alLimpiar,
    required this.alEscanear,
  });

  final TextEditingController controlador;
  final bool escaneando;
  final ValueChanged<String> alBuscar;
  final VoidCallback alLimpiar;
  final VoidCallback alEscanear;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controlador,
              onChanged: alBuscar,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Buscar producto o código',
                prefixIcon: const Icon(Icons.search, size: 21),
                suffixIcon: controlador.text.isEmpty
                    ? null
                    : IconButton(icon: const Icon(Icons.close, size: 18), onPressed: alLimpiar),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Material(
            color: escaneando ? AppColores.verde : Colors.white,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: alEscanear,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                height: 48,
                width: 48,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: escaneando ? AppColores.verde : AppColores.borde),
                ),
                child: Icon(
                  escaneando ? Icons.close : Icons.qr_code_scanner,
                  color: escaneando ? Colors.white : AppColores.verde,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Carrito extends StatelessWidget {
  const _Carrito({required this.vm, required this.alLimpiar});

  final VentaVistaModelo vm;
  final VoidCallback alLimpiar;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Text(
                          'Artículos',
                          style: TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            color: AppColores.texto,
                          ),
                        ),
                        const SizedBox(width: 10),
                        if (!vm.vacio)
                          TextButton(
                            onPressed: alLimpiar,
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              minimumSize: const Size(0, 0),
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              foregroundColor: AppColores.rojo,
                            ),
                            child: const Text(
                              'LIMPIAR',
                              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${vm.cantidadItems} ${vm.cantidadItems == 1 ? 'item' : 'items'}',
                      style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
                    ),
                  ],
                ),
                const Spacer(),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const Text(
                      'TOTAL',
                      style: TextStyle(
                        fontSize: 11,
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w700,
                        color: AppColores.textoSuave,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      dinero(vm.subtotal),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: AppColores.verde,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: vm.vacio
                ? const EstadoVacio(
                    icono: Icons.shopping_basket_outlined,
                    titulo: 'Carrito vacío',
                    detalle: 'Busque un producto o escanee su código de barras para empezar la venta.',
                  )
                : ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    itemCount: vm.items.length,
                    separatorBuilder: (_, __) => const Divider(height: 1, indent: 76),
                    itemBuilder: (context, indice) {
                      final item = vm.items[indice];
                      return _FilaCarrito(
                        item: item,
                        alSumar: () => vm.aumentar(item),
                        alRestar: () => vm.disminuir(item),
                        alQuitar: () => vm.quitar(item),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _FilaCarrito extends StatelessWidget {
  const _FilaCarrito({
    required this.item,
    required this.alSumar,
    required this.alRestar,
    required this.alQuitar,
  });

  final ItemCarrito item;
  final VoidCallback alSumar;
  final VoidCallback alRestar;
  final VoidCallback alQuitar;

  @override
  Widget build(BuildContext context) {
    return Dismissible(
      key: ValueKey('carrito-${item.productoId}'),
      direction: DismissDirection.endToStart,
      background: Container(
        color: AppColores.rojo.withValues(alpha: 0.1),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Icon(Icons.delete_outline, color: AppColores.rojo),
      ),
      onDismissed: (_) => alQuitar(),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            FotoProducto(foto: item.foto, tamano: 44),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.nombre,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w600,
                      color: AppColores.texto,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    dinero(item.precioVenta),
                    style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
                  ),
                ],
              ),
            ),
            _Contador(cantidad: item.cantidad, alSumar: alSumar, alRestar: alRestar),
            SizedBox(
              width: 74,
              child: Text(
                dinero(item.total),
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColores.verde,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Contador extends StatelessWidget {
  const _Contador({required this.cantidad, required this.alSumar, required this.alRestar});

  final int cantidad;
  final VoidCallback alSumar;
  final VoidCallback alRestar;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _BotonContador(icono: Icons.remove, alTocar: alRestar),
        Container(
          width: 34,
          alignment: Alignment.center,
          child: Text(
            '$cantidad',
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
        ),
        _BotonContador(icono: Icons.add, alTocar: alSumar),
      ],
    );
  }
}

class _BotonContador extends StatelessWidget {
  const _BotonContador({required this.icono, required this.alTocar});

  final IconData icono;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColores.fondo,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        onTap: alTocar,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          height: 30,
          width: 30,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: AppColores.borde),
          ),
          child: Icon(icono, size: 17, color: AppColores.texto),
        ),
      ),
    );
  }
}

class _ResultadosBusqueda extends StatelessWidget {
  const _ResultadosBusqueda({required this.resultados, required this.alAgregar});

  final List<Producto> resultados;
  final Future<void> Function(Producto producto, {int cantidad}) alAgregar;

  @override
  Widget build(BuildContext context) {
    if (resultados.isEmpty) {
      return const EstadoVacio(
        icono: Icons.search_off,
        titulo: 'Sin resultados',
        detalle: 'Verifique el nombre o sincronice el catálogo desde la pestaña Productos.',
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      itemCount: resultados.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, indice) {
        final producto = resultados[indice];
        final sinStock = producto.stock <= 0;

        return Tarjeta(
          alTocar: sinStock ? null : () => alAgregar(producto),
          hijo: Row(
            children: [
              FotoProducto(foto: producto.foto),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      producto.nombre,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600, color: AppColores.texto),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Text(
                          dinero(producto.precioVenta),
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: AppColores.verde,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Etiqueta(
                          texto: sinStock ? 'Sin stock' : '${producto.stock} ${producto.unidad}',
                          color: sinStock ? AppColores.rojo : AppColores.textoSuave,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(
                sinStock ? Icons.block : Icons.add_circle,
                color: sinStock ? AppColores.textoSuave : AppColores.verde,
                size: 28,
              ),
            ],
          ),
        );
      },
    );
  }
}

class _BarraCobro extends StatelessWidget {
  const _BarraCobro({
    required this.total,
    required this.items,
    required this.habilitado,
    required this.alRevisar,
  });

  final double total;
  final int items;
  final bool habilitado;
  final VoidCallback alRevisar;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColores.borde)),
      ),
      child: SizedBox(
        height: 54,
        child: ElevatedButton.icon(
          onPressed: habilitado ? alRevisar : null,
          icon: const Icon(Icons.receipt_long),
          label: Text(habilitado ? 'Revisar Orden · ${dinero(total)}' : 'Revisar Orden'),
          style: ElevatedButton.styleFrom(
            disabledBackgroundColor: AppColores.borde,
            disabledForegroundColor: AppColores.textoSuave,
          ),
        ),
      ),
    );
  }
}

class _SinPermiso extends StatelessWidget {
  const _SinPermiso({required this.mensaje});

  final String mensaje;

  @override
  Widget build(BuildContext context) {
    return EstadoVacio(
      icono: Icons.lock_outline,
      titulo: 'Acceso restringido',
      detalle: mensaje,
    );
  }
}
