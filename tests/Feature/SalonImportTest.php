<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalonImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_import_endpoint_rejects_a_missing_or_wrong_secret(): void
    {
        config(['services.salon_import.secret' => 'correct-secret']);

        $response = $this->postJson('/api/internal/salons/import-osm', ['area' => 'Richmond, VIC']);
        $response->assertUnauthorized();

        $response = $this->postJson(
            '/api/internal/salons/import-osm',
            ['area' => 'Richmond, VIC'],
            ['X-Import-Secret' => 'wrong-secret']
        );
        $response->assertUnauthorized();
    }

    public function test_a_valid_secret_imports_real_salons_from_osm(): void
    {
        config(['services.salon_import.secret' => 'correct-secret']);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'boundingbox' => ['-37.83', '-37.78', '144.97', '145.02'],
            ]], 200),
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 111,
                        'lat' => -37.81,
                        'lon' => 144.99,
                        'tags' => ['name' => 'Real Salon Co', 'shop' => 'hairdresser', 'addr:suburb' => 'Richmond', 'addr:state' => 'VIC'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson(
            '/api/internal/salons/import-osm',
            ['area' => 'Richmond, VIC'],
            ['X-Import-Secret' => 'correct-secret']
        );

        $response->assertOk();
        $response->assertJson(['imported' => 1, 'updated' => 0, 'skipped' => 0]);
        $this->assertDatabaseHas('salons', [
            'business_name' => 'Real Salon Co',
            'user_id' => null,
            'source' => 'osm_import',
        ]);
    }
}
