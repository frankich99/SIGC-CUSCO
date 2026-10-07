<?php

namespace App\Http\Controllers;

use App\Contracts\DniLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DniController extends Controller
{
    /**
     * Query citizen data by DNI.
     */
    public function lookup(Request $request, string $dni, DniLookupService $dniService): JsonResponse
    {
        $cleanDni = trim($dni);

        if (! preg_match('/^\d{8}$/', $cleanDni)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'El DNI debe contener exactamente 8 dígitos numéricos.',
            ], 422);
        }

        $person = $dniService->search($cleanDni);

        if (! $person) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No se encontraron datos para el DNI ingresado.',
            ], 404);
        }

        $personArray = $person->toArray();

        return response()->json([
            'success' => true,
            'data' => $personArray,
            'person' => $personArray,
            'message' => 'Datos obtenidos correctamente.',
        ]);
    }
}
