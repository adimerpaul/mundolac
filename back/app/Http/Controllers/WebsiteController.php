<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class WebsiteController extends Controller
{
    public function index()
    {
        $configuracion = Configuracion::first() ?? new Configuracion([
            'nombre_empresa' => 'Mundolac',
            'titulo_web' => 'Productos para tu negocio al mejor precio',
            'whatsapp' => '+591 73809946',
        ]);

        $productos = Producto::query()
            ->with('categoriaRelacion:id,nombre,color')
            ->orderBy('nombre')
            ->get();

        $categorias = Categoria::query()
            ->withCount('productos')
            ->having('productos_count', '>', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'color']);

        $ventasPorProducto = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')
            ->whereNull('venta_detalles.deleted_at')
            ->whereNotNull('venta_detalles.producto_id')
            ->selectRaw('venta_detalles.producto_id, SUM(venta_detalles.cantidad) AS cantidad_vendida')
            ->groupBy('venta_detalles.producto_id')
            ->orderByDesc('cantidad_vendida')
            ->limit(6)
            ->pluck('cantidad_vendida', 'venta_detalles.producto_id');

        $masVendidos = $this->orderedProducts($productos, $ventasPorProducto->keys()->all(), 6);

        $cotizaciones = DB::table('pedido_detalles')
            ->whereNotNull('pedido_detalles.producto_id')
            ->selectRaw('pedido_detalles.producto_id, COUNT(DISTINCT pedido_detalles.pedido_id) AS cantidad_pedidos')
            ->groupBy('pedido_detalles.producto_id')
            ->orderByDesc('cantidad_pedidos')
            ->limit(4)
            ->pluck('cantidad_pedidos', 'pedido_detalles.producto_id');

        $masCotizados = $this->orderedProducts($productos, $cotizaciones->keys()->all(), 4);

        return view('website.index', compact(
            'configuracion',
            'productos',
            'categorias',
            'masVendidos',
            'ventasPorProducto',
            'masCotizados',
            'cotizaciones',
        ));
    }

    private function orderedProducts($productos, array $ids, int $limit)
    {
        $ordered = collect($ids)
            ->map(fn ($id) => $productos->firstWhere('id', (int) $id))
            ->filter();

        if ($ordered->count() < $limit) {
            $ordered = $ordered->concat(
                $productos->whereNotIn('id', $ordered->pluck('id'))->take($limit - $ordered->count())
            );
        }

        return $ordered->take($limit)->values();
    }
}
