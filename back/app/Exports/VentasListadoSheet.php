<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class VentasListadoSheet implements WithEvents, WithTitle
{
    public function __construct(
        private readonly Collection $ventas,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function title(): string
    {
        return 'Ventas';
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $headings = ['Nº venta', 'Fecha', 'Hora', 'Usuario', 'Ítems', 'Tipo pago', 'Efectivo', 'QR', 'Subtotal', 'Descuento', 'Total'];
            if ($this->conGanancia) {
                $headings[] = 'Ganancia';
            }
            $headings[] = 'Estado';
            $last = Coordinate::stringFromColumnIndex(count($headings));

            $row = VentasExport::header($sheet, 'Reporte de ventas', $this->meta, $last);
            $sheet->fromArray($headings, null, "A{$row}");
            VentasExport::tableHeader($sheet, "A{$row}:{$last}{$row}");
            $first = $row + 1;

            foreach ($this->ventas as $venta) {
                $row++;
                $values = [
                    $venta->numero, $venta->fecha->format('d/m/Y'), $venta->fecha->format('H:i'), $venta->usuario_nombre,
                    (float) $venta->detalles->sum('cantidad'), $venta->tipo_pago,
                    (float) $venta->monto_efectivo, (float) $venta->monto_qr, (float) $venta->subtotal, (float) $venta->descuento, (float) $venta->total,
                ];
                if ($this->conGanancia) {
                    $values[] = VentasExport::ganancia($venta);
                }
                $values[] = $venta->estado;
                $sheet->fromArray($values, null, "A{$row}", true);
                if ($venta->estado !== 'COMPLETADA') {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setStrikethrough(true)->getColor()->setRGB('C62828');
                } elseif ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F5F9FD');
                }
            }
            $lastData = max($row, $first);

            // Totales: solo ventas completadas (las anuladas no suman).
            $row = $lastData + 1;
            $statusCol = $last;
            $sheet->setCellValue("A{$row}", 'TOTAL ('.$this->ventas->where('estado', 'COMPLETADA')->count().' ventas completadas)');
            $sheet->mergeCells("A{$row}:D{$row}");
            $moneyCols = $this->conGanancia ? ['E', 'G', 'H', 'I', 'J', 'K', 'L'] : ['E', 'G', 'H', 'I', 'J', 'K'];
            foreach ($moneyCols as $col) {
                $sheet->setCellValue("{$col}{$row}", "=SUMIFS({$col}{$first}:{$col}{$lastData},{$statusCol}{$first}:{$statusCol}{$lastData},\"COMPLETADA\")");
            }
            VentasExport::body($sheet, 'A'.($first - 1).":{$last}{$row}");
            VentasExport::totals($sheet, "A{$row}:{$last}{$row}");

            $endMoney = $this->conGanancia ? 'L' : 'K';
            $sheet->getStyle("G{$first}:{$endMoney}{$row}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
            $sheet->getStyle("E{$first}:E{$row}")->getNumberFormat()->setFormatCode('General');
            $sheet->getStyle("B{$first}:C{$lastData}")->getAlignment()->setHorizontal('center');
            $sheet->getStyle("E{$first}:F{$lastData}")->getAlignment()->setHorizontal('center');
            $sheet->getStyle("{$last}{$first}:{$last}{$lastData}")->getAlignment()->setHorizontal('center');
            if ($this->conGanancia) {
                $sheet->getStyle("L{$first}:L{$row}")->getFont()->setBold(true)->getColor()->setRGB('2E7D32');
            }

            foreach (range(1, count($headings)) as $i) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }
            $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(14);
            $sheet->setAutoFilter('A'.($first - 1).":{$last}{$lastData}");
            $sheet->freezePane("A{$first}");
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($first - 1, $first - 1);
            $sheet->getHeaderFooter()->setOddFooter('&L'.$this->meta['empresa'].'&RPágina &P de &N');
        }];
    }
}
