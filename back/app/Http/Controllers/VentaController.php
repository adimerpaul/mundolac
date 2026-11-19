<?php

namespace App\Http\Controllers;

use App\Exports\VentasReporteExport;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->with(['detalles:id,venta_id,nombre,cantidad,unidad', 'pedido:id,numero,cliente_nombre'])->latest('fecha');

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($this->cashSummary($this->filteredQuery($request), $request) + [
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
        $sales = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA');
        $total = (float) (clone $sales)->sum('total');
        $count = (clone $sales)->count();
        $items = (int) DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->sum('venta_detalles.cantidad');
        $profit = (float) DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->selectRaw('COALESCE(SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento), 0) AS total')
            ->value('total');

        $dailyRaw = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->where('fecha', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(fecha) as dia, SUM(total) as total')->groupBy('dia')->pluck('total', 'dia');
        $daily = collect(range(6, 0))->map(function ($days) use ($dailyRaw) {
            $date = now()->subDays($days);

            return ['label' => $date->format('d/m'), 'total' => (float) ($dailyRaw[$date->toDateString()] ?? 0)];
        });

        $byUser = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = Venta::where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->selectRaw('tipo_pago as nombre, SUM(total) as total')
            ->groupBy('tipo_pago')->get();
        $topProducts = DB::table('venta_detalles')->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->selectRaw('venta_detalles.producto_id, venta_detalles.nombre, venta_detalles.foto, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total')
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre', 'venta_detalles.foto')
            ->orderByDesc('cantidad')->limit(8)->get();

        return response()->json([
            'indicadores' => ['ventas' => $total, 'ganancia' => $profit, 'productos' => $items, 'cantidad_ventas' => $count, 'ticket_promedio' => $count ? $total / $count : 0],
            'diario' => $daily, 'usuarios' => $byUser, 'pagos' => $payments, 'productos_top' => $topProducts,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $ventas = $this->filteredQuery($request)->with('detalles')->latest('fecha')->get();

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
        ]);

        $venta = $this->ventas->registrar($request->user(), $data);

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
