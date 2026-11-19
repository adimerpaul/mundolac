<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Productos vendidos en el período, agrupados por producto (solo ventas completadas). */
class VentasProductosSheet implements WithEvents, WithTitle
{
    public function __construct(
        private readonly Collection $ventas,
        private readonly bool $conGanancia,
        private readonly array $meta,
    ) {}

    public function title(): string
    {
        return 'Productos vendidos';
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $items = $this->ventas->flatMap->detalles
                ->groupBy(fn ($d) => $d->producto_id ?: $d->codigo.'|'.$d->nombre)
                ->map(fn ($lines) => [
                    'codigo' => $lines->first()->codigo, 'nombre' => $lines->first()->nombre,
                    'categoria' => $lines->first()->categoria, 'unidad' => $lines->first()->unidad,
                    'cantidad' => (float) $lines->sum('cantidad'), 'ventas' => $lines->pluck('venta_id')->unique()->count(),
                    'descuento' => (float) $lines->sum('descuento'), 'total' => (float) $lines->sum('total'),
                    'ganancia' => round((float) $lines->sum(fn ($d) => ($d->precio_venta - $d->precio_compra) * $d->cantidad - $d->descuento), 2),
                ])
                ->sortByDesc('total')->values();

            $headings = ['#', 'Código', 'Producto', 'Categoría', 'Unidad', 'Cantidad', 'Nº ventas', 'Descuento', 'Total vendido'];
            if ($this->conGanancia) {
                $headings[] = 'Ganancia';
            }
            $last = Coordinate::stringFromColumnIndex(count($headings));

            $row = VentasExport::header($sheet, 'Productos vendidos', $this->meta, $last);
            $sheet->fromArray($headings, null, "A{$row}");
            VentasExport::tableHeader($sheet, "A{$row}:{$last}{$row}");
            $first = $row + 1;
            foreach ($items as $i => $item) {
                $row++;
                $values = [$i + 1, $item['codigo'], $item['nombre'], $item['categoria'], $item['unidad'],
                    $item['cantidad'], $item['ventas'], $item['descuento'], $item['total']];
                if ($this->conGanancia) {
                    $values[] = $item['ganancia'];
                }
                $sheet->fromArray($values, null, "A{$row}", true);
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F5F9FD');
                }
            }
            $lastData = max($row, $first);

            $row = $lastData + 1;
            $sheet->setCellValue("A{$row}", 'TOTAL ('.$items->count().' productos)');
            $sheet->mergeCells("A{$row}:E{$row}");
            foreach (array_slice(['F', 'G', 'H', 'I', 'J'], 0, $this->conGanancia ? 5 : 4) as $col) {
                if ($col !== 'G') {
                    $sheet->setCellValue("{$col}{$row}", "=SUM({$col}{$first}:{$col}{$lastData})");
                }
            }
            VentasExport::body($sheet, 'A'.($first - 1).":{$last}{$row}");
            VentasExport::totals($sheet, "A{$row}:{$last}{$row}");
            $sheet->getStyle("H{$first}:{$last}{$row}")->getNumberFormat()->setFormatCode(VentasExport::MONEY);
            $sheet->getStyle("F{$first}:F{$row}")->getNumberFormat()->setFormatCode('General');
            $sheet->getStyle("A{$first}:A{$lastData}")->getAlignment()->setHorizontal('center');
            $sheet->getStyle("E{$first}:G{$lastData}")->getAlignment()->setHorizontal('center');
            if ($this->conGanancia) {
                $sheet->getStyle("J{$first}:J{$row}")->getFont()->setBold(true)->getColor()->setRGB('2E7D32');
            }

            foreach (range(1, count($headings)) as $i) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }
            $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
            $sheet->setAutoFilter('A'.($first - 1).":{$last}{$lastData}");
            $sheet->freezePane("A{$first}");
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getHeaderFooter()->setOddFooter('&L'.$this->meta['empresa'].'&RPágina &P de &N');
        }];
    }
}
