<?php

namespace App\Models;

use App\Models\Relations\WithinDonut;
use App\Support\Geo\Coordinate;
use Database\Factories\NeighborhoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property list<array{0: float, 1: float}> $boundary
 * @property float $centroid_latitude
 * @property float $centroid_longitude
 * @property array<string, mixed> $properties
 */
#[Fillable(['id', 'name', 'boundary', 'centroid_latitude', 'centroid_longitude', 'properties'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class Neighborhood extends Model
{
    /** @use HasFactory<NeighborhoodFactory> */
    use HasFactory;

    public const float INCIDENT_INNER_RADIUS_KM = 0.5;

    public const float INCIDENT_OUTER_RADIUS_KM = 2.0;

    /**
     * Incidents in the 0.5–2 km donut around the centroid, nearest first.
     *
     * @return WithinDonut<Incident, $this>
     */
    public function incidents(): WithinDonut
    {
        return new WithinDonut(
            $this,
            Incident::class,
            innerRadiusKm: self::INCIDENT_INNER_RADIUS_KM,
            outerRadiusKm: self::INCIDENT_OUTER_RADIUS_KM,
        );
    }

    public function centroid(): Coordinate
    {
        return new Coordinate($this->centroid_latitude, $this->centroid_longitude);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'centroid_latitude' => 'float',
            'centroid_longitude' => 'float',
            'properties' => 'array',
        ];
    }
}
