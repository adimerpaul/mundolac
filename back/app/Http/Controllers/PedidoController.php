<?php

namespace App\Http\Controllers;

use App\Exports\PedidosExport;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Pedidos');
        return response()->json($this->filteredQuery($request)->withCount('detalles')->latest('fecha')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100)));
    }

    public function show(Request $request, Pedido $pedido)
    {
        $this->authorizeAction($request, 'Ver Pedidos');
        return response()->json($pedido->load('detalles', 'cliente'));
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
        $data = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'], 'fecha_entrega' => ['nullable', 'date'],
            'direccion_entrega' => ['nullable', 'string', 'max:255'], 'tipo_pago' => ['required', 'in:EFECTIVO,QR,CREDITO'],
            'latitud_entrega' => ['nullable', 'numeric', 'between:-90,90'], 'longitud_entrega' => ['nullable', 'numeric', 'between:-180,180'],
            'observacion' => ['nullable', 'string', 'max:1000'], 'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'], 'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);
        $pedido = DB::transaction(function () use ($request, $data) {
            $client = Cliente::findOrFail($data['cliente_id']);
            $total = collect($data['detalles'])->sum(fn ($d) => round($d['cantidad'] * $d['precio_unitario'], 2));
            $pedido = Pedido::create([
                'cliente_id' => $client->id, 'cliente_nombre' => $client->nombre, 'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name, 'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'direccion_entrega' => $data['direccion_entrega'] ?? $client->direccion, 'tipo_pago' => $data['tipo_pago'],
                'latitud_entrega' => $data['latitud_entrega'] ?? $client->latitud, 'longitud_entrega' => $data['longitud_entrega'] ?? $client->longitud,
                'observacion' => $data['observacion'] ?? null, 'total' => $total, 'estado' => 'PENDIENTE', 'fecha' => now(),
            ]);
            $pedido->update(['numero' => 'P-'.str_pad((string) $pedido->id, 8, '0', STR_PAD_LEFT)]);
            foreach ($data['detalles'] as $item) {
                $product = Producto::findOrFail($item['producto_id']); $quantity = round((float) $item['cantidad'], 3); $price = round((float) $item['precio_unitario'], 4);
                $pedido->detalles()->create(['producto_id' => $product->id, 'codigo' => $product->codigo, 'nombre' => $product->nombre, 'unidad' => $product->unidad, 'cantidad' => $quantity, 'precio_unitario' => $price, 'total' => round($quantity * $price, 2)]);
            }
            return $pedido;
        });
        return response()->json($pedido->load('detalles', 'cliente'), 201);
    }

    public function status(Request $request, Pedido $pedido)
    {
        $this->authorizeAction($request, 'Crear Pedidos');
        $data = $request->validate(['estado' => ['required', 'in:PENDIENTE,PREPARANDO,ENTREGADO,CANCELADO']]);
        $pedido->update($data);
        return response()->json($pedido->fresh());
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
        if ($request->filled('estado')) $query->where('estado', $request->input('estado'));
        if ($from = $request->date('desde')) $query->whereDate('fecha_entrega', '>=', $from);
        if ($to = $request->date('hasta')) $query->whereDate('fecha_entrega', '<=', $to);
        return $query;
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
