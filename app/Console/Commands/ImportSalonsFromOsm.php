<?php

namespace App\Console\Commands;

use App\Enums\SalonCategory;
use App\Models\Salon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Populates Discover with real, currently-operating hair/beauty businesses
 * from OpenStreetMap — free, no API key, no billing account. Each import
 * lands as an unclaimed Salon (user_id null, source 'osm_import'); a real
 * owner takes it over later by signing up with the same business name and
 * an admin linking the two (no self-serve "claim" flow exists yet — that's
 * the natural next step once a real salon actually wants to claim theirs).
 */
class ImportSalonsFromOsm extends Command
{
    protected $signature = 'salons:import-osm
        {area : Suburb/city and state to search, e.g. "Richmond, VIC"}
        {--limit=40 : Maximum number of businesses to import}';

    protected $description = 'Import real hair/beauty businesses near an area from OpenStreetMap into Discover';

    public function handle(): int
    {
        $area = $this->argument('area');
        $limit = (int) $this->option('limit');

        $this->info("Looking up \"{$area}\"...");
        $bbox = $this->geocode($area);

        if (! $bbox) {
            $this->error("Couldn't find that area. Try a more specific query, e.g. \"Richmond, VIC, Australia\".");

            return self::FAILURE;
        }

        $this->info('Searching OpenStreetMap for hair/beauty businesses...');
        $elements = $this->queryOverpass($bbox);

        if ($elements === null) {
            $this->error('OpenStreetMap query failed — it may be rate-limited. Try again in a minute.');

            return self::FAILURE;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($elements as $element) {
            if ($imported + $updated >= $limit) {
                break;
            }

            $tags = $element['tags'] ?? [];
            $name = trim($tags['name'] ?? '');
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;

            if (! $name || ! $lat) {
                $skipped++;

                continue;
            }

            $location = $this->buildLocation($tags) ?? $area;
            $externalRef = "osm:{$element['type']}/{$element['id']}";

            $salon = Salon::withTrashed()->where('external_ref', $externalRef)->first();
            $wasNew = ! $salon;

            Salon::withTrashed()->updateOrCreate(
                ['external_ref' => $externalRef],
                [
                    'business_name' => $name,
                    'category' => $this->mapCategory($tags),
                    'location' => $location,
                    'source' => 'osm_import',
                    'deleted_at' => null,
                ]
            );

            $wasNew ? $imported++ : $updated++;
        }

        $this->newLine();
        $this->info("Imported {$imported} new, updated {$updated} existing, skipped {$skipped} (no name or coordinates).");

        return self::SUCCESS;
    }

    /**
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    private function geocode(string $area): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Ripplebox/1.0 (capstone project; contact: 20032715@students.koi.edu.au)',
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q' => $area,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'au',
        ]);

        if (! $response->ok() || empty($response->json())) {
            return null;
        }

        $box = $response->json()[0]['boundingbox'] ?? null;

        if (! $box) {
            return null;
        }

        return [
            'south' => (float) $box[0],
            'north' => (float) $box[1],
            'west' => (float) $box[2],
            'east' => (float) $box[3],
        ];
    }

    /**
     * @param  array{south: float, west: float, north: float, east: float}  $bbox
     * @return array<int, array>|null
     */
    private function queryOverpass(array $bbox): ?array
    {
        $box = "{$bbox['south']},{$bbox['west']},{$bbox['north']},{$bbox['east']}";

        $query = <<<QL
        [out:json][timeout:25];
        (
          node["shop"="hairdresser"]({$box});
          node["shop"="beauty"]({$box});
          node["shop"="massage"]({$box});
          node["leisure"="spa"]({$box});
          way["shop"="hairdresser"]({$box});
          way["shop"="beauty"]({$box});
          way["shop"="massage"]({$box});
          way["leisure"="spa"]({$box});
        );
        out center;
        QL;

        $response = Http::withHeaders([
            'User-Agent' => 'Ripplebox/1.0 (capstone project; contact: 20032715@students.koi.edu.au)',
            'Accept' => '*/*',
        ])->asForm()
            ->timeout(30)
            ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);

        if (! $response->ok()) {
            return null;
        }

        return $response->json('elements') ?? [];
    }

    private function mapCategory(array $tags): ?string
    {
        $shop = $tags['shop'] ?? null;
        $beauty = $tags['beauty'] ?? null;
        $name = strtolower($tags['name'] ?? '');

        if ($shop === 'hairdresser') {
            return ((($tags['barber'] ?? null) === 'yes') || str_contains($name, 'barber'))
                ? SalonCategory::Barber->value
                : SalonCategory::Hair->value;
        }

        if ($shop === 'massage' || ($tags['leisure'] ?? null) === 'spa') {
            return SalonCategory::Spa->value;
        }

        if ($shop === 'beauty') {
            return match (true) {
                $beauty === 'nails' || str_contains($name, 'nail') => SalonCategory::Nails->value,
                $beauty === 'permanent_makeup' || str_contains($name, 'brow') || str_contains($name, 'lash') => SalonCategory::BrowsLashes->value,
                str_contains($name, 'makeup') => SalonCategory::Makeup->value,
                $beauty === 'tanning' || $beauty === 'facial' || str_contains($name, 'skin') => SalonCategory::Skin->value,
                default => SalonCategory::Other->value,
            };
        }

        return null;
    }

    private function buildLocation(array $tags): ?string
    {
        $street = trim(($tags['addr:housenumber'] ?? '').' '.($tags['addr:street'] ?? ''));
        $suburb = $tags['addr:suburb'] ?? $tags['addr:city'] ?? null;
        $state = $tags['addr:state'] ?? null;

        $parts = array_filter([$street ?: null, $suburb, $state]);

        return $parts ? implode(', ', $parts) : null;
    }
}
