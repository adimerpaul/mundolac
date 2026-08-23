<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cabecera, estilos y fila de totales comunes a las hojas del reporte de ventas.
 * La hoja arma su array de filas y declara qué fila es el encabezado de la tabla,
 * cuáles columnas son dinero y cuál es la fila de totales.
 */
trait HojaReporte
{
    /** Filas de título con los filtros y quién generó el reporte. */
    private function cabecera(string $titulo, int $columnas): array
    {
        $vacia = array_fill(0, $columnas, null);
        $fila = function (string $etiqueta, ?string $valor = null) use ($vacia) {
            $row = $vacia;
            $row[0] = $etiqueta;
            if ($valor !== null) {
                $row[1] = $valor;
            }

            return $row;
        };

        return [
            $fila($titulo),
            $fila($this->meta['empresa'] ?? 'MUNDOLAC'),
            $fila('Generado por:', $this->meta['generado_por'] ?? '—'),
            $fila('Generado el:', $this->meta['generado_en'] ?? '—'),
            $fila('Filtros:', $this->meta['filtros'] ?? 'Sin filtros'),
            $vacia,
        ];
    }

    /**
     * @param  string  $ultimaColumna  letra de la última columna de la tabla (ej. 'L')
     * @param  array<int, string>  $columnasDinero  letras de las columnas con importes
     */
    private function estilos(AfterSheet $event, string $ultimaColumna, int $filaEncabezado, int $filaTotales, array $columnasDinero): void
    {
        $sheet = $event->sheet->getDelegate();
        $primeraFila = $filaEncabezado + 1;
        $ultimaFila = $filaTotales - 1;

        $sheet->mergeCells("A1:{$ultimaColumna}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A3:A5')->getFont()->setBold(true);

        $sheet->getStyle("A{$filaEncabezado}:{$ultimaColumna}{$filaEncabezado}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1976D2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($filaEncabezado)->setRowHeight(20);

        if ($ultimaFila >= $primeraFila) {
            $sheet->getStyle("A{$primeraFila}:{$ultimaColumna}{$ultimaFila}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D5DBE0']]],
            ]);
            $sheet->setAutoFilter("A{$filaEncabezado}:{$ultimaColumna}{$ultimaFila}");
        }

        $sheet->getStyle("A{$filaTotales}:{$ultimaColumna}{$filaTotales}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '90A4AE']]],
        ]);

        foreach ($columnasDinero as $columna) {
            $sheet->getStyle("{$columna}{$primeraFila}:{$columna}{$filaTotales}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $sheet->freezePane("A{$primeraFila}");
        $this->ajustarAncho($sheet, $ultimaColumna);
    }

    private function ajustarAncho(Worksheet $sheet, string $ultimaColumna): void
    {
        foreach (range('A', $ultimaColumna) as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }
    }
}
