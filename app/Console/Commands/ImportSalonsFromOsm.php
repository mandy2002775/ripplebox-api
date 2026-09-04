<?php

namespace App\Console\Commands;

use App\Services\SalonImportService;
use Illuminate\Console\Command;

class ImportSalonsFromOsm extends Command
{
    protected $signature = 'salons:import-osm
        {area : Suburb/city and state to search, e.g. "Richmond, VIC"}
        {--limit=40 : Maximum number of businesses to import}';

    protected $description = 'Import real hair/beauty businesses near an area from OpenStreetMap into Discover';

    public function handle(SalonImportService $importer): int
    {
        $area = $this->argument('area');

        $this->info("Looking up \"{$area}\" and searching OpenStreetMap for hair/beauty businesses...");
        $result = $importer->importArea($area, (int) $this->option('limit'));

        if ($result['error'] === 'area_not_found') {
            $this->error("Couldn't find that area. Try a more specific query, e.g. \"Richmond, VIC, Australia\".");

            return self::FAILURE;
        }

        if ($result['error'] === 'osm_query_failed') {
            $this->error('OpenStreetMap query failed — it may be rate-limited. Try again in a minute.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Imported {$result['imported']} new, updated {$result['updated']} existing, skipped {$result['skipped']} (no name or coordinates).");

        return self::SUCCESS;
    }
}
