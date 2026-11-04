<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Support\Geo\Coordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevealDonutFlagCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_the_codes_of_donut_incidents_nearest_first(): void
    {
        $centroid = new Coordinate(33.33, 44.3525);
        Neighborhood::factory()->centeredAt($centroid)->create(['name' => 'NB-7A2F']);

        Incident::factory()->kmFrom($centroid, eastKm: 1.5)->withCode('}')->create();
        Incident::factory()->kmFrom($centroid, northKm: 0.6)->withCode('{')->create();
        Incident::factory()->kmFrom($centroid, northKm: 0.3)->withCode('x')->create();

        $this->artisan('illuminate:donut-flag')
            ->expectsOutput('{}')
            ->assertSuccessful();
    }

    public function test_it_fails_for_an_unknown_neighborhood(): void
    {
        $this->artisan('illuminate:donut-flag', ['neighborhood' => 'NB-0000'])->assertFailed();
    }
}
