<?php

namespace Database\Factories;

use App\Models\Neighborhood;
use App\Support\Geo\Coordinate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $centroid = new Coordinate(
            fake()->latitude(33.25, 33.40),
            fake()->longitude(44.30, 44.45),
        );

        return [
            'id' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => 'NB-'.fake()->unique()->regexify('[0-9A-F]{4}'),
            ...$this->squareAround($centroid),
            'properties' => [
                'zone' => fake()->randomElement(['east', 'west']),
                'area_km2' => fake()->randomFloat(1, 1, 10),
                'population' => fake()->numberBetween(10_000, 150_000),
            ],
        ];
    }

    public function centeredAt(Coordinate $centroid): static
    {
        return $this->state(fn (): array => $this->squareAround($centroid));
    }

    /**
     * @return array{boundary: list<array{0: float, 1: float}>, centroid_latitude: float, centroid_longitude: float}
     */
    private function squareAround(Coordinate $centroid, float $halfSideInDegrees = 0.01): array
    {
        return [
            'boundary' => [
                [$centroid->latitude - $halfSideInDegrees, $centroid->longitude - $halfSideInDegrees],
                [$centroid->latitude - $halfSideInDegrees, $centroid->longitude + $halfSideInDegrees],
                [$centroid->latitude + $halfSideInDegrees, $centroid->longitude + $halfSideInDegrees],
                [$centroid->latitude + $halfSideInDegrees, $centroid->longitude - $halfSideInDegrees],
            ],
            'centroid_latitude' => $centroid->latitude,
            'centroid_longitude' => $centroid->longitude,
        ];
    }
}
