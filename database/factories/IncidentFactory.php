<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Support\Geo\Coordinate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 1_000_000),
            'latitude' => fake()->latitude(33.25, 33.40),
            'longitude' => fake()->longitude(44.30, 44.45),
            'metadata' => [
                'notes' => 'Incident report #'.fake()->numberBetween(10_000, 99_999),
                'incident' => [
                    'code' => fake()->randomLetter(),
                    'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
                    'classification' => 'noise-'.fake()->numberBetween(0, 199),
                ],
                'responder' => [
                    'unit' => 'UNIT-'.fake()->numerify('##'),
                    'dispatch_time' => fake()->dateTimeThisYear()->format(DATE_ATOM),
                ],
            ],
            'tags' => fake()->randomElements(['fire', 'medical', 'theft', 'accident', 'pending', 'resolved'], 2),
            'reports' => [],
            'occurred_at' => fake()->dateTimeThisYear(),
        ];
    }

    public function at(Coordinate $coordinate): static
    {
        return $this->state(fn (): array => [
            'latitude' => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
        ]);
    }

    /**
     * Place the incident the given distance due north or east of an origin.
     */
    public function kmFrom(Coordinate $origin, float $northKm = 0.0, float $eastKm = 0.0): static
    {
        return $this->at(new Coordinate(
            $origin->latitude + $northKm / Coordinate::KM_PER_DEGREE,
            $origin->longitude + $eastKm / $origin->kmPerDegreeOfLongitude(),
        ));
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => array_replace_recursive($attributes['metadata'], ['incident' => ['code' => $code]]),
        ]);
    }
}
