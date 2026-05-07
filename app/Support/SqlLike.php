<?php

namespace App\Support;

/**
 * SqlLike — helper para escape de wildcards SQL en queries con `LIKE %q%`.
 *
 * PROBLEMA (post-auditoría 2026-05-06, MEDIUM #7):
 *   Cuando se usa `where('nombre', 'like', "%{$q}%")` con bindings parametrizados,
 *   no hay SQL injection. PERO los caracteres `%` y `_` dentro de `$q` son wildcards
 *   en SQL LIKE — significan "cualquier secuencia" y "cualquier caracter" respectivamente.
 *
 *   Buscar "100%" matchea CUALQUIER fila que contenga "100" seguido de cualquier cosa.
 *   Buscar "_" matchea CUALQUIER fila con cualquier carácter en cualquier parte.
 *
 *   Un usuario hostil con sesión podría forzar full-table scans en consejos_comunales (2207 filas)
 *   con queries como `?q=_` repetidamente — DoS leve por degradación.
 *
 * MITIGACIÓN:
 *   Escape de los 3 caracteres especiales de LIKE: `%`, `_`, `\`.
 *
 * USO:
 *
 *   use App\Support\SqlLike;
 *   $q = SqlLike::escape(trim($filters['q']));
 *   $w->where('nombre', 'like', "%{$q}%");
 *
 * Referencias:
 *   - CWE-1289: Improper Validation of Unsafe Equivalence in Input
 */
class SqlLike
{
    /**
     * Escapa los wildcards de SQL LIKE: `%`, `_`, `\`.
     *
     * El backslash debe escaparse PRIMERO (sino los siguientes \% se romperían).
     */
    public static function escape(string $value): string
    {
        // addcslashes lo hace de forma segura: \, %, _ → \\, \%, \_
        return addcslashes($value, '%_\\');
    }
}
