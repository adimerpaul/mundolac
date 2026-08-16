<?php

namespace App\Http\Controllers;

use App\Exports\ProductosVendidosExport;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    /** Cantidad vendida del producto en el rango (0 cuando nunca se vendió). */
    private const CANTIDAD = 'COALESCE(x.cantidad, 0)';

    /** Precio de compra promedio ponderado del rango; si no hubo ventas, el precio actual del producto. */
    private const COMPRA = 'COALESCE(x.precio_compra_prom, productos.precio_compra)';

    /** Precio de venta promedio ponderado del rango; si no hubo ventas, el precio actual del producto. */
    private const VENTA = 'COALESCE(x.precio_venta_prom, productos.precio_venta)';

    public function productosVendidos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Reportes');
        $query = $this->productosQuery($request);

        $totals = DB::query()->fromSub((clone $query)->reorder(), 't')
            ->selectRaw('COUNT(*) as productos, COALESCE(SUM(cantidad), 0) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(ganancia), 0) as ganancia, COALESCE(SUM(descuento), 0) as descuento')
            ->first();

        $chart = (clone $query)->limit(10)->get()->map(fn ($p) => [
            'nombre' => $p->nombre,
            'cantidad' => (float) $p->cantidad,
            'total' => (float) $p->total,
            'ganancia' => (float) $p->ganancia,
        ])->values();

        $perPage = (int) $request->input('per_page', 20);
        $productos = $query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500));

        return response()->json([
            'productos' => $productos,
            'resumen' => [
                'productos' => (int) $totals->productos,
                'cantidad' => (float) $totals->cantidad,
                'total' => (float) $totals->total,
                'ganancia' => (float) $totals->ganancia,
                'descuento' => (float) $totals->descuento,
            ],
            'grafico' => $chart,
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function productosVendidosExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Reportes');
        $rows = $this->productosQuery($request)->limit(5000)->get();

        return Excel::download(new ProductosVendidosExport($rows), 'productos_vendidos_'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * Agrega las ventas completadas por producto y aplica los filtros del reporte.
     * La agregación va en una subconsulta para que los productos sin ventas sigan
     * apareciendo (opción "menos vendidos") y para poder filtrar/ordenar por columna.
     */
    private function productosQuery(Request $request)
    {
        $sales = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->where('v.estado', 'COMPLETADA')
            ->whereNull('v.deleted_at')
            ->whereNull('vd.deleted_at')
            ->whereNotNull('vd.producto_id')
            ->groupBy('vd.producto_id')
            ->selectRaw('vd.producto_id,
                SUM(vd.cantidad) as cantidad,
                SUM(vd.subtotal) as subtotal,
                SUM(vd.descuento) as descuento,
                SUM(vd.total) as total,
                SUM(((vd.precio_venta - vd.precio_compra) * vd.cantidad) - vd.descuento) as ganancia,
                COUNT(DISTINCT vd.venta_id) as ventas,
                MAX(v.fecha) as ultima_venta,
                SUM(vd.precio_compra * vd.cantidad) / NULLIF(SUM(vd.cantidad), 0) as precio_compra_prom,
                SUM(vd.precio_venta * vd.cantidad) / NULLIF(SUM(vd.cantidad), 0) as precio_venta_prom');

        if ($from = $request->date('desde')) {
            $sales->whereDate('v.fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $sales->whereDate('v.fecha', '<=', $to);
        }
        if ($userId = $request->integer('user_id')) {
            $sales->where('v.user_id', $userId);
        }

        $margen = '('.self::VENTA.' - '.self::COMPRA.')';
        $query = DB::table('productos')
            ->leftJoinSub($sales, 'x', 'x.producto_id', '=', 'productos.id')
            ->leftJoin('categorias as c', 'c.id', '=', 'productos.categoria_id')
            ->whereNull('productos.deleted_at')
            ->selectRaw('productos.id, productos.codigo, productos.codigo_barras, productos.nombre, productos.unidad,
                productos.foto, productos.stock_inicial,
                COALESCE(c.nombre, productos.categoria) as categoria, c.color as categoria_color,
                productos.precio_compra as precio_compra_actual, productos.precio_venta as precio_venta_actual,
                '.self::CANTIDAD.' as cantidad,
                COALESCE(x.subtotal, 0) as subtotal,
                COALESCE(x.descuento, 0) as descuento,
                COALESCE(x.total, 0) as total,
                COALESCE(x.ganancia, 0) as ganancia,
                COALESCE(x.ventas, 0) as ventas,
                x.ultima_venta,
                '.self::COMPRA.' as precio_compra,
                '.self::VENTA.' as precio_venta,
                '.$margen.' as margen');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('productos.nombre', 'like', "%{$search}%")
                ->orWhere('productos.codigo', 'like', "%{$search}%")
                ->orWhere('productos.codigo_barras', 'like', "%{$search}%"));
        }
        if ($categoriaId = $request->integer('categoria_id')) {
            $query->where('productos.categoria_id', $categoriaId);
        }
        if (! $request->boolean('incluir_sin_ventas')) {
            $query->whereRaw(self::CANTIDAD.' > 0');
        }

        $ranges = [
            'cantidad' => self::CANTIDAD,
            'precio_compra' => self::COMPRA,
            'precio_venta' => self::VENTA,
            'total' => 'COALESCE(x.total, 0)',
        ];
        foreach ($ranges as $field => $expression) {
            if (is_numeric($min = $request->input($field.'_min'))) {
                $query->whereRaw("{$expression} >= ?", [(float) $min]);
            }
            if (is_numeric($max = $request->input($field.'_max'))) {
                $query->whereRaw("{$expression} <= ?", [(float) $max]);
            }
        }

        $sortable = [
            'cantidad' => self::CANTIDAD,
            'total' => 'COALESCE(x.total, 0)',
            'ganancia' => 'COALESCE(x.ganancia, 0)',
            'ventas' => 'COALESCE(x.ventas, 0)',
            'precio_compra' => self::COMPRA,
            'precio_venta' => self::VENTA,
            'margen' => $margen,
            'stock' => 'productos.stock_inicial',
            'nombre' => 'productos.nombre',
            'ultima_venta' => 'x.ultima_venta',
        ];
        $order = $sortable[$request->input('orden')] ?? self::CANTIDAD;
        $direction = strtolower((string) $request->input('direccion')) === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw("{$order} {$direction}")->orderBy('productos.nombre');
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
