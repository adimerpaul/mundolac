<?php

namespace App\Exports;

use App\Exports\Concerns\HojaReporte;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Hoja "Ventas": una fila por venta con fecha, hora, quién la registró y sus importes. */
class VentasHojaExport implements FromArray, WithEvents, WithTitle
{
    use HojaReporte;

    private const ENCABEZADOS = ['Nº Venta', 'Fecha', 'Hora', 'Registrado por', 'Tipo pago', 'Efectivo', 'QR',
        'Ítems', 'Productos', 'Subtotal', 'Descuento', 'Total', 'Estado', 'Observación'];

    private int $filaEncabezado = 0;

    private int $filaTotales = 0;

    /** @param  Collection<int, Venta>  $ventas */
    public function __construct(private readonly Collection $ventas, private readonly array $meta) {}

    public function title(): string
    {
        return 'Ventas';
    }

    public function array(): array
    {
        $columnas = count(self::ENCABEZADOS);
        $filas = $this->cabecera('REPORTE DE VENTAS', $columnas);
        $this->filaEncabezado = count($filas) + 1;
        $filas[] = self::ENCABEZADOS;

        foreach ($this->ventas as $venta) {
            $detalles = $venta->detalles;
            // Los egresos salen de caja: se muestran en negativo para que las columnas sumen neto.
            $signo = $venta->tipo === 'EGRESO' ? -1 : 1;
            $filas[] = [
                $venta->numero,
                $venta->fecha?->format('d/m/Y'),
                $venta->fecha?->format('H:i:s'),
                $venta->usuario_nombre,
                $venta->tipo === 'VENTA' ? $venta->tipo_pago : "{$venta->tipo} · {$venta->tipo_pago}",
                $signo * (float) $venta->monto_efectivo,
                $signo * (float) $venta->monto_qr,
                (float) $detalles->sum('cantidad'),
                $detalles->map(fn ($d) => trim($d->cantidad.' '.$d->nombre))->implode(', '),
                (float) $venta->subtotal,
                (float) $venta->descuento,
                $signo * (float) $venta->total,
                $venta->estado,
                $venta->observacion,
            ];
        }

        // Los importes solo suman movimientos completados: los anulados no mueven dinero.
        // Efectivo y QR = caja neta (ventas cobradas + ingresos − egresos); Total = lo vendido.
        $completadas = $this->ventas->where('estado', 'COMPLETADA');
        $neto = fn (string $campo) => (float) $completadas->sum(fn ($v) => ($v->tipo === 'EGRESO' ? -1 : 1) * (float) $v->{$campo});
        $soloVentas = $completadas->where('tipo', 'VENTA');
        $this->filaTotales = count($filas) + 1;
        $filas[] = [
            'TOTALES', null, null, $soloVentas->count().' ventas', null,
            $neto('monto_efectivo'),
            $neto('monto_qr'),
            (float) $completadas->sum(fn ($v) => $v->detalles->sum('cantidad')),
            null,
            (float) $soloVentas->sum('subtotal'),
            (float) $soloVentas->sum('descuento'),
            (float) $soloVentas->sum('total'),
            null, null,
        ];

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->estilos(
                $event, 'N', $this->filaEncabezado, $this->filaTotales, ['F', 'G', 'J', 'K', 'L']
            ),
        ];
    }
}
