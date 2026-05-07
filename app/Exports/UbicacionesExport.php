<?php

namespace App\Exports;

use App\Support\ExcelSanitizer as E;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * UbicacionesExport — exporta la jerarquía de ubicaciones a Excel con styling institucional.
 *
 * Mismo patrón que ReportesExport (Fase 12): cintillo + subtítulo + headers verdes + freeze + autoFilter.
 *
 * Columnas: Estado · Municipio · Parroquia · Comuna · Consejo Comunal · Reportes asociados
 */
class UbicacionesExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithEvents,
    WithTitle,
    WithCustomStartCell
{
    private const HEADER_ROW = 4;
    private const FIRST_DATA_ROW = 5;
    private const LAST_COLUMN = 'F';

    /**
     * @param  Collection  $rows  Filas planas con keys: estado, municipio, parroquia, comuna, consejo, reportes_count
     * @param  array  $filtrosLegibles  Filtros aplicados para mostrar en el subtítulo
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filtrosLegibles = [],
    ) {
    }

    public function startCell(): string
    {
        return 'A' . self::HEADER_ROW;
    }

    public function collection(): Collection
    {
        // ExcelSanitizer (E::clean) neutraliza CSV Formula Injection.
        // Aunque las ubicaciones son master data del Estado (poco probable de inyectar),
        // el principio "defense in depth" aplica: sanitizar TODOS los exports sin excepción.
        return $this->rows->map(fn ($r) => [
            E::clean($r['estado'] ?? 'Táchira'),
            E::clean($r['municipio'] ?? ''),
            E::clean($r['parroquia'] ?? ''),
            E::clean($r['comuna'] ?? ''),
            E::clean($r['consejo'] ?? ''),
            $r['reportes_count'] ?? 0,
        ]);
    }

    public function headings(): array
    {
        return [
            'Estado',
            'Municipio',
            'Parroquia',
            'Comuna',
            'Consejo Comunal',
            'Reportes asociados',
        ];
    }

    public function title(): string
    {
        return 'Ubicaciones ' . now()->format('Y-m-d');
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            self::HEADER_ROW => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalFilas = $this->rows->count();
                $lastDataRow = $totalFilas + self::HEADER_ROW;

                // ─── Fila 1: cintillo institucional ───
                $cintilloPath = public_path('images/cintillo-institucional.png');
                if (file_exists($cintilloPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Cintillo CVAUP Táchira');
                    $drawing->setDescription('República Bolivariana de Venezuela · Ministerio del Poder Popular para las Comunas · CVAUP');
                    $drawing->setPath($cintilloPath);
                    $drawing->setHeight(75);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(4);
                    $drawing->setOffsetY(4);
                    $drawing->setWorksheet($sheet);
                }
                $sheet->getRowDimension(1)->setRowHeight(65);

                // ─── Fila 2: subtítulo institucional ───
                $sheet->setCellValue(
                    'A2',
                    'Listado de Ubicaciones · Corporación Venezolana de Agricultura Urbana y Periurbana Táchira'
                );
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '64748B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // ─── Fila 3: filtros aplicados + metadata ───
                $textoFiltros = empty($this->filtrosLegibles)
                    ? 'Sin filtros — listado completo'
                    : 'Filtrado por: ' . implode(' · ', $this->filtrosLegibles);

                $sheet->setCellValue(
                    'A3',
                    sprintf(
                        '%s · Generado: %s · Total: %d ubicación(es)',
                        $textoFiltros,
                        now()->format('d/m/Y H:i'),
                        $totalFilas,
                    )
                );
                $sheet->mergeCells('A3:F3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '94A3B8']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(15);

                // ─── Fila 4: headers (alto + autoFilter + freeze) ───
                $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(28);
                $sheet->freezePane('A' . self::FIRST_DATA_ROW);
                $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . self::LAST_COLUMN . self::HEADER_ROW);

                // ─── Fila 5+: estilo de datos ───
                if ($totalFilas > 0) {
                    $rangeFull = sprintf('A%d:%s%d', self::HEADER_ROW, self::LAST_COLUMN, $lastDataRow);
                    $rangeData = sprintf('A%d:%s%d', self::FIRST_DATA_ROW, self::LAST_COLUMN, $lastDataRow);

                    $sheet->getStyle($rangeFull)
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->setColor(new Color('FFE2E8F0'));

                    $sheet->getStyle($rangeData)
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_TOP)
                        ->setWrapText(true);

                    // Centrar la columna de Reportes
                    $sheet->getStyle("F" . self::FIRST_DATA_ROW . ":F{$lastDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Filas alternadas
                    for ($row = self::FIRST_DATA_ROW; $row <= $lastDataRow; $row++) {
                        if (($row - self::FIRST_DATA_ROW) % 2 === 1) {
                            $sheet->getStyle("A{$row}:" . self::LAST_COLUMN . $row)
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()
                                ->setRGB('F8FAFC');
                        }
                    }
                }
            },
        ];
    }
}
