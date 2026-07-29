<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#263238}h2{margin:0;color:#1565c0}.meta{margin:4px 0 12px;color:#607d8b}
table{width:100%;border-collapse:collapse}th{background:#1565c0;color:#fff;padding:6px;text-align:left}td{padding:5px;border-bottom:1px solid #ddd}.right{text-align:right}.summary{margin-top:12px;text-align:right;font-size:11px}
</style></head><body>
<h2>{{ mb_strtoupper($configuracion->nombre_empresa ?? 'Mundolac') }} — REPORTE DE PEDIDOS</h2>
<div class="meta">@if($configuracion?->nit)NIT: {{ $configuracion->nit }} · @endif Generado: {{ now()->format('d/m/Y H:i') }}</div>
<table><thead><tr><th>N.º</th><th>Fecha y hora</th><th>Cliente</th><th>Entrega</th><th>Pago</th><th>Productos</th><th class="right">Total</th><th>Estado</th></tr></thead>
<tbody>@foreach($pedidos as $p)<tr><td>{{$p->numero}}</td><td>{{$p->fecha->format('d/m/Y H:i')}}</td><td>{{$p->cliente_nombre}}</td><td>{{$p->fecha_entrega?->format('d/m/Y') ?? '—'}}</td><td>{{$p->tipo_pago}}</td><td>{{$p->detalles_count}}</td><td class="right">Bs {{number_format($p->total,2)}}</td><td>{{$p->estado}}</td></tr>@endforeach</tbody></table>
<div class="summary"><b>Pedidos: {{$pedidos->count()}} &nbsp; Total: Bs {{number_format($pedidos->where('estado','!=','CANCELADO')->sum('total'),2)}}</b></div>
</body></html>
