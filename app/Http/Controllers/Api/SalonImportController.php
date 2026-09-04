<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SalonImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a real OpenStreetMap import be triggered on production, which has no
 * shell access to run `salons:import-osm` directly. Same shared-secret
 * pattern as WebhookController — not tied to any user account.
 */
class SalonImportController extends Controller
{
    public function __invoke(Request $request, SalonImportService $importer): JsonResponse
    {
        $secret = config('services.salon_import.secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Import-Secret'))) {
            abort(401, 'Invalid import secret.');
        }

        $data = $request->validate([
            'area' => ['required', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $importer->importArea($data['area'], $data['limit'] ?? 40);

        return response()->json($result, $result['error'] ? 422 : 200);
    }
}
