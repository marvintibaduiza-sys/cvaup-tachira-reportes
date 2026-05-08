<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comuna;
use App\Models\ConsejoComunal;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints JSON para selects en cascada (Reportes).
 *
 * Diseño: una sola ruta + parámetro `nivel` mantiene el código compacto;
 * el frontend invoca según el nivel necesario.
 *
 * Rutas (en routes/web.php, dentro del middleware auth):
 *   GET /api/parroquias?municipio_id=X
 *   GET /api/comunas?parroquia_id=X
 *   GET /api/consejos?comuna_id=X
 */
class UbicacionLookupController extends Controller
{
    public function municipios(): JsonResponse
    {
        return response()->json(
            Municipio::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    /**
     * Lista parroquias filtradas por uno O VARIOS municipios.
     *
     * Acepta:
     *   ?municipio_id=5           (singular, compat con Reportes form que es cascada simple)
     *   ?municipio_ids[]=5&municipio_ids[]=8   (multi, usado por filtros de Generar Reportes)
     *
     * Si se envían ambos, prioriza el array.
     */
    public function parroquias(Request $request): JsonResponse
    {
        $request->validate([
            'municipio_id' => 'nullable|integer|exists:municipios,id',
            'municipio_ids' => 'nullable|array',
            'municipio_ids.*' => 'integer|exists:municipios,id',
        ]);

        $ids = $request->input('municipio_ids');
        if (!is_array($ids) || empty($ids)) {
            // Fallback al singular (compat con form de reportes)
            $singular = $request->integer('municipio_id');
            $ids = $singular ? [$singular] : [];
        }

        if (empty($ids)) {
            return response()->json([]);
        }

        return response()->json(
            Parroquia::whereIn('municipio_id', $ids)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
        );
    }

    public function comunas(Request $request): JsonResponse
    {
        $request->validate(['parroquia_id' => 'required|integer|exists:parroquias,id']);

        return response()->json(
            Comuna::where('parroquia_id', $request->integer('parroquia_id'))
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
        );
    }

    public function consejos(Request $request): JsonResponse
    {
        $request->validate(['comuna_id' => 'required|integer|exists:comunas,id']);

        return response()->json(
            ConsejoComunal::where('comuna_id', $request->integer('comuna_id'))
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
        );
    }
}
