<?php

namespace App\Models;

use App\Support\Geo\Coordinate;
use Carbon\CarbonImmutable;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property float $latitude
 * @property float $longitude
 * @property array<string, mixed> $metadata
 * @property list<string> $tags
 * @property list<array<string, mixed>> $reports
 * @property CarbonImmutable $occurred_at
 * @property-read string|null $code
 */
#[Fillable(['id', 'latitude', 'longitude', 'metadata', 'tags', 'reports', 'occurred_at'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public function coordinate(): Coordinate
    {
        return new Coordinate($this->latitude, $this->longitude);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function code(): Attribute
    {
        return Attribute::get(fn (): ?string => data_get($this->metadata, 'incident.code'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
            'tags' => 'array',
            'reports' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
