<?php

namespace App\Http\Controllers;

use App\Exports\PedidosExport;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\VentaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PedidoController extends Controller
{
    /** Estados en los que el pedido todavía se puede editar. */
    private const EDITABLES = ['PENDIENTE', 'PREPARANDO'];

    public function __construct(private VentaService $ventas) {}

    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');

        return response()->json($this->filteredQuery($request)->withCount('detalles')->with('venta:id,pedido_id,numero,estado,total,monto_pagado,fecha_pago')->latest('fecha')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100)));
    }

    public function show(Request $request, Pedido $pedido)
    {
        $this->authorizeAction($request, 'Ver Pedidos');

        return response()->json($pedido->load('detalles', 'cliente', 'venta:id,pedido_id,numero,estado,total,monto_pagado,tipo_pago,fecha_pago', 'venta.pagos'));
    }

    /** Deudores: ventas con saldo pendiente (pedidos entregados) y los pagos ya recibidos. */
    public function debtors(Request $request)
    {
        $this->authorizeAction($request, 'Ver Deudores');
        $query = Venta::with(['pedido:id,numero,cliente_id,cliente_nombre,fecha_entrega,direccion_entrega', 'pagos' => fn ($q) => $q->where('estado', 'COMPLETADO')->orderBy('fecha')])
            ->where('tipo', 'VENTA')->where('estado', 'COMPLETADA')->whereNull('fecha_pago');
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhereHas('pedido', fn ($p) => $p->where('numero', 'like', "%{$search}%")->orWhere('cliente_nombre', 'like', "%{$search}%")));
        }
        $deudas = $query->orderBy('fecha')->limit(500)
            ->get(['id', 'numero', 'pedido_id', 'usuario_nombre', 'total', 'monto_pagado', 'fecha', 'observacion']);

        return response()->json([
            'deudas' => $deudas,
            'total' => round((float) $deudas->sum('saldo'), 2),
            'clientes' => $deudas->groupBy(fn ($v) => $v->pedido?->cliente_nombre ?? 'SIN PEDIDO')
                ->map(fn ($g, $nombre) => [
                    'cliente' => $nombre, 'cantidad' => $g->count(), 'total' => round((float) $g->sum('saldo'), 2),
                    // Desde cuándo debe: la entrega más antigua que sigue sin pagarse.
                    'desde' => $g->min('fecha')?->toDateTimeString(), 'dias' => (int) $g->min('fecha')?->startOfDay()->diffInDays(today()),
                ])
                ->sortByDesc('total')->values(),
        ]);
    }

    public function clients(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');
        $date = $request->date('fecha')?->toDateString() ?? today()->toDateString();
        $clients = Cliente::where('activo', true)
            ->withCount(['pedidos as pedidos_fecha_count' => fn ($q) => $q
                ->whereDate('fecha', $date)->where('estado', '!=', 'CANCELADO')])
            ->orderBy('nombre')->get();
        $clients->each(function ($client) {
            $client->setAttribute('tiene_pedido', (int) $client->pedidos_fecha_count > 0);
            $client->setAttribute('cantidad_pedidos_fecha', (int) $client->pedidos_fecha_count);
        });

        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Pedidos');
        $data = $request->validate($this->rules());
        $pedido = DB::transaction(function () use ($request, $data) {
            $client = Cliente::findOrFail($data['cliente_id']);
            $pedido = Pedido::create([
                'cliente_id' => $client->id, 'cliente_nombre' => $client->nombre, 'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name, 'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'direccion_entrega' => $data['direccion_entrega'] ?? $client->direccion, 'tipo_pago' => $data['tipo_pago'],
                'latitud_entrega' => $data['latitud_entrega'] ?? $client->latitud, 'longitud_entrega' => $data['longitud_entrega'] ?? $client->longitud,
                'observacion' => $data['observacion'] ?? null, 'total' => 0, 'estado' => 'PENDIENTE', 'fecha' => now(),
            ]);
            $pedido->update(['numero' => 'P-'.str_pad((string) $pedido->id, 8, '0', STR_PAD_LEFT)]);
            $this->syncDetalles($pedido, $data['detalles']);

            return $pedido;
        });

        return response()->json($pedido->load('detalles', 'cliente'), 201);
    }

    public function update(Request $request, Pedido $pedido)
    {
        $this->authorizeAction($request, 'Crear Pedidos');
        $data = $request->validate($this->rules());
        $pedido = DB::transaction(function () use ($data, $pedido) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($pedido->estado, self::EDITABLES, true), 422, "Solo se pueden editar pedidos PENDIENTES o en PREPARACIÓN (este está {$pedido->estado})");
            $client = Cliente::findOrFail($data['cliente_id']);
            $pedido->update([
                'cliente_id' => $client->id, 'cliente_nombre' => $client->nombre, 'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'direccion_entrega' => $data['direccion_entrega'] ?? $client->direccion, 'tipo_pago' => $data['tipo_pago'],
                'latitud_entrega' => $data['latitud_entrega'] ?? $client->latitud, 'longitud_entrega' => $data['longitud_entrega'] ?? $client->longitud,
                'observacion' => $data['observacion'] ?? null,
            ]);
            $pedido->detalles()->delete();
            $this->syncDetalles($pedido, $data['detalles']);

            return $pedido;
        });

        return response()->json($pedido->load('detalles', 'cliente'));
    }

    /**
     * Cambia el estado. Al marcar ENTREGADO se registra la venta (con pedido_id) que
     * descuenta stock y lotes; si no hay stock, el pedido no cambia de estado.
     * La venta queda SIEMPRE con deuda: no entra dinero a caja hasta registrar el
     * cobro (tabla pagos), que puede ser en otro momento y en abonos.
     * Un pedido ENTREGADO solo se revierte anulando su venta (vuelve a PENDIENTE).
     */
    public function status(Request $request, Pedido $pedido)
    {
        $this->authorizeAction($request, 'Crear Pedidos');
        $data = $request->validate([
            'estado' => ['required', 'in:PENDIENTE,PREPARANDO,ENTREGADO,CANCELADO'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
        ]);
        if ($data['estado'] === 'ENTREGADO') {
            $this->authorizeAction($request, 'Crear Ventas');
        }

        $pedido = DB::transaction(function () use ($request, $data, $pedido) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if ($pedido->estado === $data['estado']) {
                return $pedido;
            }
            abort_if($pedido->estado === 'ENTREGADO', 422, 'El pedido ya fue entregado y tiene una venta registrada. Anule la venta para revertirlo.');

            if ($data['estado'] === 'ENTREGADO') {
                $pedido->load('detalles');
                abort_if($pedido->detalles->contains(fn ($d) => ! $d->producto_id), 422, 'El pedido contiene productos que ya no existen; edítelo antes de entregarlo');
                $this->ventas->registrar($request->user(), [
                    'pedido_id' => $pedido->id,
                    'descuento' => $data['descuento'] ?? 0,
                    'pagado' => false,
                    'observacion' => mb_substr(trim("Entrega del pedido {$pedido->numero} ({$pedido->cliente_nombre}) con deuda. ".($pedido->observacion ?? '')), 0, 1000),
                    'detalles' => $pedido->detalles->map(fn ($d) => [
                        'producto_id' => $d->producto_id, 'cantidad' => $d->cantidad, 'precio_venta' => $d->precio_unitario,
                    ])->all(),
                ]);
            }

            $pedido->update(['estado' => $data['estado']]);

            return $pedido;
        });

        return response()->json($pedido->fresh()->load('venta:id,pedido_id,numero,estado,total,monto_pagado,tipo_pago,fecha_pago'));
    }

    private function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'], 'fecha_entrega' => ['nullable', 'date'],
            'direccion_entrega' => ['nullable', 'string', 'max:255'], 'tipo_pago' => ['required', 'in:EFECTIVO,QR,CREDITO'],
            'latitud_entrega' => ['nullable', 'numeric', 'between:-90,90'], 'longitud_entrega' => ['nullable', 'numeric', 'between:-180,180'],
            'observacion' => ['nullable', 'string', 'max:1000'], 'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'], 'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** Crea los detalles desnormalizando el producto y recalcula el total del pedido. */
    private function syncDetalles(Pedido $pedido, array $detalles): void
    {
        $total = 0;
        foreach ($detalles as $item) {
            $product = Producto::findOrFail($item['producto_id']);
            $quantity = round((float) $item['cantidad'], 3);
            $price = round((float) $item['precio_unitario'], 4);
            $lineTotal = round($quantity * $price, 2);
            $pedido->detalles()->create(['producto_id' => $product->id, 'codigo' => $product->codigo, 'nombre' => $product->nombre, 'unidad' => $product->unidad, 'cantidad' => $quantity, 'precio_unitario' => $price, 'total' => $lineTotal]);
            $total += $lineTotal;
        }
        $pedido->update(['total' => round($total, 2)]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');
        $pedidos = $this->filteredQuery($request)->withCount('detalles')->latest('fecha')->get();

        return Excel::download(new PedidosExport($pedidos), 'pedidos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');
        $pedidos = $this->filteredQuery($request)->withCount('detalles')->latest('fecha')->get();
        $configuracion = Configuracion::first();

        return Pdf::loadView('pedidos.reporte', compact('pedidos', 'configuracion'))->setPaper('letter', 'landscape')
            ->download('pedidos_'.now()->format('Ymd_His').'.pdf');
    }

    public function printData(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');

        return response()->json($this->filteredQuery($request)
            ->with('detalles')->latest('fecha')->limit(500)->get());
    }

    private function filteredQuery(Request $request)
    {
        $query = Pedido::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")->orWhere('cliente_nombre', 'like', "%{$search}%")->orWhere('estado', 'like', "%{$search}%"));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha_entrega', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha_entrega', '<=', $to);
        }

        return $query;
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
