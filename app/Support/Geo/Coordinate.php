<?php

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * Distances use the equirectangular approximation: one degree of latitude is
 * KM_PER_DEGREE km and longitude is scaled by cos(latitude of the origin).
 * At city scale (a few km) the error versus haversine is negligible, and the
 * resulting formula needs only arithmetic, so it runs on any SQL engine.
 */
final readonly class Coordinate
{
    public const float KM_PER_DEGREE = 111.0;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * Parse every "(x,y)" pair of a PostgreSQL geometric literal (point, path or polygon).
     *
     * The challenge dataset stores latitude in x and longitude in y.
     *
     * @return list<self>
     */
    public static function allFromPostgres(string $geometry): array
    {
        $number = '[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?';

        preg_match_all("/\(\s*({$number})\s*,\s*({$number})\s*\)/", $geometry, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            throw new InvalidArgumentException("Unable to parse PostgreSQL geometry [{$geometry}].");
        }

        return array_map(fn (array $match): self => new self((float) $match[1], (float) $match[2]), $matches);
    }

    /**
     * @param  non-empty-list<self>  $coordinates
     */
    public static function boundingBoxCenter(array $coordinates): self
    {
        $latitudes = array_map(fn (self $coordinate): float => $coordinate->latitude, $coordinates);
        $longitudes = array_map(fn (self $coordinate): float => $coordinate->longitude, $coordinates);

        return new self(
            (min($latitudes) + max($latitudes)) / 2,
            (min($longitudes) + max($longitudes)) / 2,
        );
    }

    /**
     * Kilometres per degree of longitude at this latitude.
     */
    public function kmPerDegreeOfLongitude(): float
    {
        return self::KM_PER_DEGREE * cos(deg2rad($this->latitude));
    }

    public function squaredDistanceInKmTo(self $other): float
    {
        return (($other->latitude - $this->latitude) * self::KM_PER_DEGREE) ** 2
            + (($other->longitude - $this->longitude) * $this->kmPerDegreeOfLongitude()) ** 2;
    }

    public function distanceInKmTo(self $other): float
    {
        return sqrt($this->squaredDistanceInKmTo($other));
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function toArray(): array
    {
        return [$this->latitude, $this->longitude];
    }
}
