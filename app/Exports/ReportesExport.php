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
 * ReportesExport — exporta una colección de reportes a Excel (.xlsx) con styling institucional.
 *
 * Layout del worksheet:
 *   Fila 1:   Cintillo institucional CVAUP (imagen anclada a A1, ~75px alto)
 *   Fila 2:   Subtítulo "Sistema de Reportes Técnicos · CVAUP Táchira"
 *   Fila 3:   Spacer + metadata "Generado: dd/mm/yyyy hh:mm · N reportes"
 *   Fila 4:   Headers de columnas (verde institucional, blanco bold)
 *   Fila 5+:  Datos
 *
 * Características:
 *  - Encoding UTF-8 nativo
 *  - Anchos de columna auto-ajustados
 *  - Freeze pane en A5 (cintillo + subtítulo + headers fijos al hacer scroll)
 *  - AutoFilter en row 4 (botones de filtro en headers)
 *  - Filas alternadas (gris claro cada 2da fila de datos)
 *  - Borde sutil en todas las celdas con datos
 */
class ReportesExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize,
    WithEvents,
    WithTitle,
    WithCustomStartCell
{
    private const HEADER_ROW = 4;       // fila donde van los headers de columna
    private const FIRST_DATA_ROW = 5;   // primera fila de datos
    // BLOQUE 9: 20 columnas (A=1..T=20). Antes eran 22.
    // Quitadas en BLOQUE 9: "Otras comunas atendidas" y "Otros consejos comunales atendidos"
    // (eran columnas concatenadas desde los pivotes que se eliminaron).
    private const LAST_COLUMN = 'T';

    public function __construct(private readonly Collection $reportes)
    {
    }

    /**
     * Indica a Maatwebsite que los headings + datos comienzan en A4 (no A1),
     * dejando A1:A3 libres para el cintillo, subtítulo y spacer.
     */
    public function startCell(): string
    {
        return 'A' . self::HEADER_ROW;
    }

    public function collection(): Collection
    {
        return $this->reportes->map(function ($r) {
            $especialidades = \is_array($r->tecnico?->especialidades)
                ? implode(', ', $r->tecnico->especialidades)
                : '';

            return [
                $r->id,
                $r->fecha?->format('Y-m-d'),
                E::clean($r->estado_reporte),
                // Tecnico
                E::clean($r->tecnico?->nombre_apellido),
                E::clean($r->tecnico?->tipo_documento),
                E::clean($r->tecnico?->cedula),
                E::clean($especialidades),
                // Ubicacion principal
                E::clean($r->municipio?->nombre),
                E::clean($r->parroquia?->nombre),
                E::clean($r->comuna?->nombre),
                E::clean($r->consejoComunal?->nombre),
                // BLOQUE 9: cantidades manuales (totales, incluyen la principal)
                $r->cantidad_comunas_atendidas,
                $r->cantidad_consejos_comunales_atendidos,
                E::clean($r->lugar),
                // Personas
                $r->cantidad_personas_atendidas,
                $r->cantidad_personas_a_beneficiar,
                // BLOQUE 8: actividad simplificada
                E::clean($r->tipo_actividad),
                E::clean($r->descripcion_actividad),
                // Meta
                $r->fotos->count(),
                $r->created_at?->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID', 'Fecha', 'Estado del reporte',
            // Técnico
            'Técnico', 'Tipo doc.', 'Cédula', 'Especialidades',
            // Ubicación principal
            'Municipio', 'Parroquia', 'Comuna', 'Consejo Comunal',
            // BLOQUE 9: cantidades manuales totales
            'Total comunas atendidas', 'Total consejos comunales atendidos',
            'Lugar',
            // Personas
            'Personas atendidas', 'Personas a beneficiar',
            // BLOQUE 8: actividad
            'Tipo de actividad', 'Descripción de la actividad',
            // Meta
            'Cantidad de fotos', 'Fecha creación',
        ];
    }

    public function title(): string
    {
        return 'Reportes ' . now()->format('Y-m-d');
    }

    /**
     * Styling de filas específicas. Aplicamos el verde institucional al header (fila 4).
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            self::HEADER_ROW => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1B5E20'],
                ],
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
                $totalReportes = $this->reportes->count();
                $lastDataRow = $totalReportes + self::HEADER_ROW; // fila final con datos

                // ─── Fila 1: cintillo institucional como imagen anclada a A1 ───
                $cintilloPath = public_path('images/cintillo-institucional.png');
                if (file_exists($cintilloPath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Cintillo CVAUP Táchira');
                    $drawing->setDescription('República Bolivariana de Venezuela · Ministerio del Poder Popular para las Comunas · CVAUP');
                    $drawing->setPath($cintilloPath);
                    $drawing->setHeight(75); // ancho se calcula proporcional al ratio 5.58:1 ≈ 419px
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(4);
                    $drawing->setOffsetY(4);
                    $drawing->setWorksheet($sheet);
                }

                // Row 1 height: suficiente para que el cintillo (75px) no se sobreponga
                // 75px / 1.333 = 56pt, le damos 65pt para tener respiración visual
                $sheet->getRowDimension(1)->setRowHeight(65);

                // ─── Fila 2: subtítulo institucional ───
                $sheet->setCellValue(
                    'A2',
                    'Sistema de Reportes Técnicos · Corporación Venezolana de Agricultura Urbana y Periurbana Táchira'
                );
                $sheet->mergeCells('A2:H2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'italic' => true,
                        'color' => ['rgb' => '64748B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // ─── Fila 3: metadata "Generado: ... · N reporte(s)" ───
                $sheet->setCellValue(
                    'A3',
                    sprintf('Generado: %s · %d reporte(s)', now()->format('d/m/Y H:i'), $totalReportes)
                );
                $sheet->mergeCells('A3:H3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => [
                        'size' => 9,
                        'color' => ['rgb' => '94A3B8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(15);

                // ─── Fila 4: headers — altura más alta para que el verde se note ───
                $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(28);

                // Freeze pane después de los headers (filas 1-4 quedan fijas al hacer scroll vertical)
                $sheet->freezePane('A' . self::FIRST_DATA_ROW);

                // AutoFilter en la fila de headers
                $sheet->setAutoFilter('A' . self::HEADER_ROW . ':' . self::LAST_COLUMN . self::HEADER_ROW);

                // ─── Fila 5+: estilo de las filas de datos ───
                if ($totalReportes > 0) {
                    $rangeData = sprintf(
                        'A%d:%s%d',
                        self::FIRST_DATA_ROW,
                        self::LAST_COLUMN,
                        $lastDataRow
                    );
                    $rangeFull = sprintf(
                        'A%d:%s%d',
                        self::HEADER_ROW,
                        self::LAST_COLUMN,
                        $lastDataRow
                    );

                    // Borde sutil gris en todas las celdas con datos (incluyendo headers)
                    $sheet->getStyle($rangeFull)
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->setColor(new Color('FFE2E8F0'));

                    // Vertical-align top + wrap text para texto largo
                    $sheet->getStyle($rangeData)
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_TOP)
                        ->setWrapText(true);

                    // Filas alternadas (gris claro cada 2da fila de datos)
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
