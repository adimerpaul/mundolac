<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Exports\VentasExport;
=======
use App\Exports\VentasReporteExport;
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
<<<<<<< HEAD
use App\Models\VentaDetalle;
=======
use App\Services\VentaService;
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class VentaController extends Controller
{
    public function __construct(private VentaService $ventas) {}

    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)
<<<<<<< HEAD
            ->withCount('detalles')
            ->with('detalles:id,venta_id,nombre,unidad,cantidad,total')
            ->when($request->user()->hasPermissionTo('Ver Ganancias'), fn ($q) => $q->addSelect(['ganancia' => VentaDetalle::selectRaw(
                'COALESCE(SUM(((precio_venta - precio_compra) * cantidad) - descuento), 0)'
            )->whereColumn('venta_detalles.venta_id', 'ventas.id')]))
            ->latest('fecha');
=======
            ->with(['detalles:id,venta_id,nombre,cantidad,unidad', 'pedido:id,numero,cliente_nombre'])->latest('fecha');
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');

<<<<<<< HEAD
        return response()->json([
            'efectivo' => (clone $query)->sum('monto_efectivo'),
            'qr' => (clone $query)->sum('monto_qr'),
            'total' => (clone $query)->sum('total'),
            'descuento' => (clone $query)->sum('descuento'),
            'cantidad' => (clone $query)->count(),
            'ganancia' => $request->user()->hasPermissionTo('Ver Ganancias')
                ? round((float) VentaDetalle::whereIn('venta_id', (clone $query)->select('id'))
                    ->selectRaw('COALESCE(SUM(((precio_venta - precio_compra) * cantidad) - descuento), 0) AS total')
                    ->value('total'), 2)
                : null,
=======
        return response()->json($this->cashSummary($this->filteredQuery($request), $request) + [
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Caja del rango: pagos recibidos (contado + cobros de deudas, por la fecha del
     * pago) + ingresos − egresos. Una venta con deuda no suma dinero hasta cobrarla.
     */
    private function cashSummary($query, Request $request): array
    {
        $base = (clone $query)->where('estado', 'COMPLETADA');
        $sum = fn (string $tipo, string $column) => (float) (clone $base)->where('tipo', $tipo)->sum($column);
        $pagos = $this->pagosQuery($request);
        $sumPagos = fn (string $column, ?string $tipo = null) => (float) (clone $pagos)->when($tipo, fn ($q) => $q->where('tipo', $tipo))->sum($column);
        $efectivo = $sumPagos('monto_efectivo') + $sum('INGRESO', 'monto_efectivo') - $sum('EGRESO', 'monto_efectivo');
        $qr = $sumPagos('monto_qr') + $sum('INGRESO', 'monto_qr') - $sum('EGRESO', 'monto_qr');

        return [
            'ventas' => $sum('VENTA', 'total'),
            'credito' => round((float) (clone $base)->where('tipo', 'VENTA')->whereNull('fecha_pago')->sum(DB::raw('total - monto_pagado')), 2),
            'cobros' => $sumPagos('monto', 'COBRO'),
            'ingresos' => $sum('INGRESO', 'total'),
            'egresos' => $sum('EGRESO', 'total'),
            'efectivo' => round($efectivo, 2),
            'qr' => round($qr, 2),
            'total' => round($efectivo + $qr, 2),
            'descuento' => $sum('VENTA', 'descuento'),
            'cantidad' => (clone $base)->where('tipo', 'VENTA')->count(),
        ];
    }

    /** Pagos vigentes filtrados con los mismos criterios de fecha/hora/usuario que las ventas. */
    private function pagosQuery(Request $request)
    {
        $query = Pago::where('estado', 'COMPLETADO');
        if ($request->filled('tipo') && $request->input('tipo') !== 'VENTA') {
            return $query->whereRaw('1 = 0');
        }
        if ($search = trim((string) $request->input('q'))) {
            $query->whereHas('venta', fn ($v) => $v->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")->orWhere('observacion', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($fromTime = $this->parseTime($request->input('hora_desde'), '00')) {
            $query->whereTime('fecha', '>=', $fromTime);
        }
        if ($toTime = $this->parseTime($request->input('hora_hasta'), '59')) {
            $query->whereTime('fecha', '<=', $toTime);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    public function dashboard(Request $request)
    {
        $this->authorizeAction($request, 'Ver Panel');
<<<<<<< HEAD
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
        $from = $request->filled('desde') ? Carbon::parse($request->input('desde'))->startOfDay() : null;
        $to = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->endOfDay() : null;
        $range = fn ($query, string $column = 'fecha') => $query
            ->when($from, fn ($q) => $q->where($column, '>=', $from))
            ->when($to, fn ($q) => $q->where($column, '<=', $to));
        $sales = $range(Venta::where('estado', 'COMPLETADA'));
        $details = fn () => $range(DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at'), 'ventas.fecha');

=======
        $sales = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA');
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
        $total = (float) (clone $sales)->sum('total');
        $count = (clone $sales)->count();
        $items = (float) $details()->sum('venta_detalles.cantidad');
        $canProfit = $request->user()->hasPermissionTo('Ver Ganancias');
        $profit = $canProfit
            ? (float) $details()
                ->selectRaw('COALESCE(SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento), 0) AS total')
                ->value('total')
            : null;

<<<<<<< HEAD
        // Gráfico: por hora si es un solo día, por mes si el rango supera 62 días, si no por día.
        // Sin rango → últimos 7 días.
        $chartFrom = $from ?? now()->subDays(6)->startOfDay();
        $chartTo = $to ?? now()->endOfDay();
        if ($chartFrom->isSameDay($chartTo)) {
            [$bucket, $detailBucket] = ['HOUR(fecha)', 'HOUR(ventas.fecha)'];
        } elseif ($chartFrom->diffInDays($chartTo) > 62) {
            [$bucket, $detailBucket] = ["DATE_FORMAT(fecha, '%Y-%m')", "DATE_FORMAT(ventas.fecha, '%Y-%m')"];
        } else {
            [$bucket, $detailBucket] = ['DATE(fecha)', 'DATE(ventas.fecha)'];
        }
        $salesBy = (clone $sales)->where('fecha', '>=', $chartFrom)->where('fecha', '<=', $chartTo)
            ->selectRaw("$bucket as periodo, SUM(total) as total")->groupByRaw($bucket)->pluck('total', 'periodo');
        $profitBy = $canProfit
            ? $details()->where('ventas.fecha', '>=', $chartFrom)->where('ventas.fecha', '<=', $chartTo)
                ->selectRaw("$detailBucket as periodo, SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento) as total")
                ->groupByRaw($detailBucket)->pluck('total', 'periodo')
            : collect();
=======
        $dailyRaw = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->where('fecha', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(fecha) as dia, SUM(total) as total')->groupBy('dia')->pluck('total', 'dia');
        $daily = collect(range(6, 0))->map(function ($days) use ($dailyRaw) {
            $date = now()->subDays($days);
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2

        if ($bucket === 'HOUR(fecha)') {
            // Horario comercial 06–22, ampliado si hubo ventas fuera de él.
            $hours = $salesBy->keys()->map(fn ($h) => (int) $h);
            $points = collect(range(min(6, $hours->min() ?? 6), max(22, $hours->max() ?? 22)))
                ->map(fn ($h) => [sprintf('%02d:00', $h), $h]);
        } elseif (str_starts_with($bucket, 'DATE_FORMAT')) {
            $points = collect(CarbonPeriod::create($chartFrom->copy()->startOfMonth(), '1 month', $chartTo))
                ->map(fn ($date) => [$date->format('m/Y'), $date->format('Y-m')]);
        } else {
            $points = collect(CarbonPeriod::create($chartFrom->copy()->startOfDay(), '1 day', $chartTo))
                ->map(fn ($date) => [$date->format('d/m'), $date->toDateString()]);
        }
        $daily = $points->map(fn ($point) => [
            'label' => $point[0],
            'total' => (float) ($salesBy[$point[1]] ?? 0),
            'ganancia' => $canProfit ? round((float) ($profitBy[$point[1]] ?? 0), 2) : null,
        ])->values();

<<<<<<< HEAD
        $byUser = (clone $sales)->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = (clone $sales)->selectRaw('tipo_pago as nombre, SUM(total) as total')
=======
        $byUser = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->selectRaw('tipo_pago as nombre, SUM(total) as total')
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
            ->groupBy('tipo_pago')->get();
        $topProducts = $details()
            ->selectRaw('venta_detalles.producto_id, venta_detalles.nombre, venta_detalles.foto, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total')
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre', 'venta_detalles.foto')
            ->orderByDesc('cantidad')->limit(8)->get();

        return response()->json([
            'indicadores' => ['ventas' => $total, 'ganancia' => $profit, 'productos' => $items, 'cantidad_ventas' => $count, 'ticket_promedio' => $count ? $total / $count : 0],
            'diario' => $daily, 'agrupado' => match (true) {
                str_starts_with($bucket, 'HOUR') => 'hora', str_starts_with($bucket, 'DATE_FORMAT') => 'mes', default => 'dia'
            }, 'usuarios' => $byUser, 'pagos' => $payments, 'productos_top' => $topProducts,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $ventas = $this->filteredQuery($request)->with('detalles')->latest('fecha')->get();

<<<<<<< HEAD
        $ventas = $this->filteredQuery($request)->with('detalles')->latest('fecha')->get();
        $config = Configuracion::first();
        $from = $request->date('desde');
        $to = $request->date('hasta');
        $periodo = match (true) {
            $from && $to && $from->isSameDay($to) => $from->format('d/m/Y'),
            $from && $to => $from->format('d/m/Y').' al '.$to->format('d/m/Y'),
            (bool) $from => 'Desde '.$from->format('d/m/Y'),
            (bool) $to => 'Hasta '.$to->format('d/m/Y'),
            default => 'Todas las fechas',
        };
        $meta = [
            'empresa' => $config?->nombre_empresa ?: 'Mundolac',
            'nit' => $config?->nit,
            'periodo' => $periodo,
            'usuario' => $request->integer('user_id') ? User::find($request->integer('user_id'))?->name : null,
            'generado_por' => $request->user()->name ?: $request->user()->username,
        ];

        return Excel::download(
            new VentasExport($ventas, $request->user()->hasPermissionTo('Ver Ganancias'), $meta),
            'ventas_'.now()->format('Ymd_His').'.xlsx'
        );
=======
        return Excel::download(
            new VentasReporteExport($ventas, $this->exportMeta($request)),
            'ventas_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /** Datos de cabecera del Excel: empresa, quién lo generó y los filtros aplicados. */
    private function exportMeta(Request $request): array
    {
        $filtros = [];
        if ($desde = $request->date('desde')) {
            $filtros[] = 'Desde '.$desde->format('d/m/Y');
        }
        if ($hasta = $request->date('hasta')) {
            $filtros[] = 'Hasta '.$hasta->format('d/m/Y');
        }
        if ($horaDesde = $this->parseTime($request->input('hora_desde'), '00')) {
            $filtros[] = 'Hora desde '.substr($horaDesde, 0, 5);
        }
        if ($horaHasta = $this->parseTime($request->input('hora_hasta'), '59')) {
            $filtros[] = 'Hora hasta '.substr($horaHasta, 0, 5);
        }
        if ($tipo = $request->input('tipo')) {
            $filtros[] = 'Tipo: '.$tipo;
        }
        if ($userId = $request->integer('user_id')) {
            $filtros[] = 'Usuario: '.(User::find($userId)?->name ?? $userId);
        }
        if ($search = trim((string) $request->input('q'))) {
            $filtros[] = 'Búsqueda: '.$search;
        }

        return [
            'empresa' => Configuracion::first()?->nombre_empresa ?? 'MUNDOLAC',
            'generado_por' => $request->user()?->name ?? '—',
            'generado_en' => now()->format('d/m/Y H:i:s'),
            'filtros' => $filtros ? implode(' · ', $filtros) : 'Sin filtros (todas las ventas)',
        ];
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $ventas = $this->filteredQuery($request)->latest('fecha')->get();
        $resumen = $this->cashSummary($this->filteredQuery($request), $request);

        return Pdf::loadView('ventas.reporte', compact('ventas', 'resumen'))->setPaper('letter', 'landscape')
            ->download('ventas_'.now()->format('Ymd_His').'.pdf');
    }

    public function show(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($venta->load('detalles', 'pedido:id,numero,cliente_nombre', 'pagos'));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Ventas');
        $data = $request->validate([
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.precio_venta' => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_compra' => ['nullable', 'numeric', 'min:0'],
        ]);
        $canSetCost = $request->user()->hasPermissionTo('Ver Ganancias');

<<<<<<< HEAD
        $venta = DB::transaction(function () use ($request, $data, $canSetCost) {
            $items = [];
            $subtotal = 0;
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->findOrFail($detail['producto_id']);
                $quantity = round((float) $detail['cantidad'], 3);
                abort_if((float) $product->stock_inicial + 0.0001 < $quantity, 422, "Stock insuficiente para {$product->nombre}");
                $salePrice = round((float) $detail['precio_venta'], 4);
                $lineSubtotal = round($salePrice * $quantity, 2);
                $subtotal += $lineSubtotal;
                $costPrice = $canSetCost && isset($detail['precio_compra']) && $detail['precio_compra'] !== ''
                    ? round((float) $detail['precio_compra'], 2)
                    : (float) $product->precio_compra;
                $items[] = [$product, $quantity, $salePrice, $lineSubtotal, $costPrice];
            }

            $discount = round((float) ($data['descuento'] ?? 0), 2);
            abort_if($discount > $subtotal, 422, 'El descuento no puede superar el subtotal');
            $total = round($subtotal - $discount, 2);
            $cash = $data['tipo_pago'] === 'EFECTIVO' ? $total : round((float) ($data['monto_efectivo'] ?? 0), 2);
            $qr = $data['tipo_pago'] === 'QR' ? $total : round((float) ($data['monto_qr'] ?? 0), 2);
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la venta');

            $sale = Venta::create([
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'subtotal' => $subtotal,
                'descuento' => $discount,
                'total' => $total,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
            ]);
            $sale->update(['numero' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);

            $allocated = 0;
            foreach ($items as $index => [$product, $quantity, $salePrice, $lineSubtotal, $costPrice]) {
                $lineDiscount = $index === array_key_last($items)
                    ? $discount - $allocated
                    : round($discount * ($lineSubtotal / $subtotal), 2);
                $allocated += $lineDiscount;
                $saleDetail = $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'codigo_barras' => $product->codigo_barras,
                    'nombre' => $product->nombre,
                    'categoria' => $product->categoria,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'precio_compra' => $costPrice,
                    'precio_venta' => $salePrice,
                    'cantidad' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'descuento' => $lineDiscount,
                    'total' => $lineSubtotal - $lineDiscount,
                ]);
                $product->decrement('stock_inicial', $quantity);
                $remaining = $quantity;
                $lots = Lote::where('producto_id', $product->id)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderByRaw('fecha_vencimiento IS NULL')
                    ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();
                foreach ($lots as $lot) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    $taken = min($remaining, (float) $lot->cantidad_disponible);
                    $lot->decrement('cantidad_disponible', $taken);
                    DB::table('venta_detalle_lotes')->insert([
                        'venta_detalle_id' => $saleDetail->id, 'lote_id' => $lot->id,
                        'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $remaining = round($remaining - $taken, 3);
                }
            }

            return $sale;
        });
=======
        $venta = $this->ventas->registrar($request->user(), $data);
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2

        return response()->json($venta->load('detalles'), 201);
    }

    private function filteredQuery(Request $request)
    {
        $query = Venta::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('estado', 'like', "%{$search}%")
                ->orWhere('observacion', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        // La hora se aplica como franja horaria del día: cada fecha del rango
        // se filtra entre hora_desde y hora_hasta (por defecto 00:01 y 23:59).
        if ($fromTime = $this->parseTime($request->input('hora_desde'), '00')) {
            $query->whereTime('fecha', '>=', $fromTime);
        }
        if ($toTime = $this->parseTime($request->input('hora_hasta'), '59')) {
            $query->whereTime('fecha', '<=', $toTime);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        return $query;
    }

    private function parseTime($value, string $defaultSeconds): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $value), $m)) {
            return null;
        }
        if ((int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? $defaultSeconds);
    }

    public function cancel(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Anular Ventas');
        abort_if($venta->estado === 'ANULADA', 422, 'La venta ya está anulada');
        $cobros = $venta->pagos()->where('tipo', 'COBRO')->where('estado', 'COMPLETADO')->count();
        abort_if($cobros > 0, 422, 'Esta venta tiene cobros registrados. Anule primero los cobros.');

        DB::transaction(function () use ($venta) {
            foreach ($venta->detalles as $detail) {
                Producto::whereKey($detail->producto_id)->increment('stock_inicial', $detail->cantidad);
                foreach (DB::table('venta_detalle_lotes')->where('venta_detalle_id', $detail->id)->get() as $allocation) {
                    Lote::whereKey($allocation->lote_id)->increment('cantidad_disponible', $allocation->cantidad);
                }
            }
            $venta->update(['estado' => 'ANULADA']);
            $venta->pagos()->where('estado', 'COMPLETADO')->update(['estado' => 'ANULADO']);
            // Si la venta vino de un pedido, el pedido vuelve a quedar pendiente de entrega.
            if ($venta->tipo === 'VENTA' && $venta->pedido_id) {
                Pedido::whereKey($venta->pedido_id)->where('estado', 'ENTREGADO')->update(['estado' => 'PENDIENTE']);
            }
        });

        return response()->json($venta->fresh());
    }

    public function changePayment(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Cambiar Pago Ventas');
        $data = $request->validate([
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = DB::transaction(function () use ($request, $venta, $data) {
            // Se relee con bloqueo: si llegan dos peticiones a la vez (doble clic),
            // la segunda espera y encuentra pago_cambiado = true.
            $sale = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();

            abort_if($sale->pago_cambiado, 422, 'El método de pago de esta venta ya fue cambiado una vez y no se puede volver a modificar.');
            abort_if($sale->estado !== 'COMPLETADA', 422, 'Solo se puede cambiar el método de pago de una venta COMPLETADA.');
            abort_if($sale->tipo_pago === 'CREDITO', 422, 'Una venta a crédito se salda registrando su cobro en Deudores.');
            abort_if($sale->tipo_pago === $data['tipo_pago'], 422, "La venta ya está registrada como {$data['tipo_pago']}.");

            $total = round((float) $sale->total, 2);
            $cash = match ($data['tipo_pago']) {
                'EFECTIVO' => $total,
                'QR' => 0.0,
                default => round((float) ($data['monto_efectivo'] ?? 0), 2),
            };
            $qr = match ($data['tipo_pago']) {
                'QR' => $total,
                'EFECTIVO' => 0.0,
                default => round((float) ($data['monto_qr'] ?? 0), 2),
            };
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la venta');

            $sale->update([
                'tipo_pago_original' => $sale->tipo_pago,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'pago_cambiado' => true,
                'pago_cambiado_en' => now(),
                'pago_cambiado_user_id' => $request->user()->id,
                'pago_cambiado_por' => $request->user()->name,
                'pago_cambiado_motivo' => $data['motivo'] ?? null,
            ]);
            // El pago al contado registrado con la venta refleja el nuevo método.
            $sale->pagos()->where('tipo', 'CONTADO')->where('estado', 'COMPLETADO')
                ->update(['tipo_pago' => $data['tipo_pago'], 'monto_efectivo' => $cash, 'monto_qr' => $qr]);

            return $sale;
        });

        return response()->json($updated);
    }

    /**
     * Registra un cobro (total o un abono) de una venta con deuda en la tabla pagos.
     * Entra en la caja del día en que se cobra.
     */
    public function registerPayment(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Crear Ventas');
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $pago = $this->ventas->cobrar($request->user(), $venta, $data);

        return response()->json($pago->load('venta:id,numero,total,monto_pagado,fecha_pago'), 201);
    }

    /** Anula un cobro de deuda: la venta vuelve a tener ese saldo pendiente. */
    public function cancelPayment(Request $request, Pago $pago)
    {
        $this->authorizeAction($request, 'Anular Ventas');

        return response()->json($this->ventas->anularCobro($pago));
    }

    /** Ingreso o egreso manual de caja (sin productos). */
    public function storeMovement(Request $request)
    {
        $this->authorizeAction($request, 'Registrar Ingresos Egresos');
        $data = $request->validate([
            'tipo' => ['required', 'in:INGRESO,EGRESO'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($this->ventas->movimiento($request->user(), $data), 201);
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
