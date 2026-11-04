<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('illuminate:donut-flag {neighborhood=NB-7A2F : Name of the neighborhood}')]
#[Description('Concatenate the codes of incidents in the donut around a neighborhood centroid, nearest first')]
class RevealDonutFlagCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $neighborhood = Neighborhood::query()->firstWhere('name', $this->argument('neighborhood'));

        if ($neighborhood === null) {
            $this->components->error("Neighborhood [{$this->argument('neighborhood')}] not found. Run `php artisan illuminate:import` first.");

            return self::FAILURE;
        }

        $incidents = $neighborhood->incidents;

        if ($incidents->isEmpty()) {
            $this->components->warn('No incidents found in the donut.');

            return self::FAILURE;
        }

        $centroid = $neighborhood->centroid();

        $this->components->twoColumnDetail('Centroid', sprintf('%.6f, %.6f', $centroid->latitude, $centroid->longitude));
        $this->components->twoColumnDetail('Donut', sprintf('%.1f km – %.1f km', Neighborhood::INCIDENT_INNER_RADIUS_KM, Neighborhood::INCIDENT_OUTER_RADIUS_KM));

        if ($this->output->isVerbose()) {
            $this->table(['#', 'Incident', 'Distance (km)', 'Code'], $incidents->values()->map(fn (Incident $incident, int $index): array => [
                $index + 1,
                $incident->id,
                sprintf('%.4f', $centroid->distanceInKmTo($incident->coordinate())),
                $incident->code,
            ])->all());
        }

        $this->newLine();
        $this->line($incidents->map(fn (Incident $incident): string => (string) $incident->code)->implode(''));

        return self::SUCCESS;
    }
}
