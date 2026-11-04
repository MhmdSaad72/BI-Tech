<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Services\Illuminate\ChallengeDatabase;
use App\Support\Geo\Coordinate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

#[Signature('illuminate:import')]
#[Description('Import neighborhoods and incidents from the challenge database into the local database')]
class ImportChallengeDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ChallengeDatabase $database): int
    {
        [$neighborhoods, $incidents] = $database->using(fn (Connection $remote): array => $remote->transaction(function () use ($remote): array {
            $remote->statement('SET TRANSACTION READ ONLY');

            return [
                $remote->select(<<<'SQL'
                    select id, name, boundary::text as boundary, properties::text as properties
                    from gis_data.neighborhoods
                    order by id
                    SQL),
                $remote->select(<<<'SQL'
                    select id,
                           location::text as location,
                           metadata::text as metadata,
                           to_jsonb(tags)::text as tags,
                           to_jsonb(reports)::text as reports,
                           to_char(occurred_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') as occurred_at
                    from gis_data.incidents
                    order by id
                    SQL),
            ];
        }));

        DB::transaction(function () use ($neighborhoods, $incidents): void {
            $this->importNeighborhoods($neighborhoods);
            $this->importIncidents($incidents);
        });

        $this->components->info(sprintf('Imported %d neighborhoods and %d incidents.', count($neighborhoods), count($incidents)));

        return self::SUCCESS;
    }

    /**
     * @param  list<object{id: int, name: string, boundary: string, properties: string}>  $rows
     */
    private function importNeighborhoods(array $rows): void
    {
        $records = array_map(function (object $row): array {
            $boundary = Coordinate::allFromPostgres($row->boundary);
            $centroid = Coordinate::boundingBoxCenter($boundary);

            return [
                'id' => $row->id,
                'name' => $row->name,
                'boundary' => json_encode(array_map(fn (Coordinate $vertex): array => $vertex->toArray(), $boundary)),
                'centroid_latitude' => $centroid->latitude,
                'centroid_longitude' => $centroid->longitude,
                'properties' => $row->properties,
            ];
        }, $rows);

        Neighborhood::query()->upsert($records, ['id']);
        Neighborhood::query()->whereNotIn('id', array_column($records, 'id'))->delete();
    }

    /**
     * @param  list<object{id: int, location: string, metadata: string, tags: string, reports: string, occurred_at: string}>  $rows
     */
    private function importIncidents(array $rows): void
    {
        $records = array_map(function (object $row): array {
            [$location] = Coordinate::allFromPostgres($row->location);

            return [
                'id' => $row->id,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'metadata' => $row->metadata,
                'tags' => $row->tags,
                'reports' => $row->reports,
                'occurred_at' => $row->occurred_at,
            ];
        }, $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            Incident::query()->upsert($chunk, ['id']);
        }

        Incident::query()->whereNotIn('id', array_column($records, 'id'))->delete();
    }
}
