<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Models\Relations\WithinDonut;
use App\Support\Geo\Coordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WithinDonutRelationTest extends TestCase
{
    use RefreshDatabase;

    private Coordinate $centroid;

    private Neighborhood $neighborhood;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centroid = new Coordinate(33.33, 44.3525);
        $this->neighborhood = Neighborhood::factory()->centeredAt($this->centroid)->create();
    }

    public function test_it_returns_only_incidents_inside_the_donut_ordered_by_distance(): void
    {
        $far = Incident::factory()->kmFrom($this->centroid, eastKm: -1.99)->withCode('C')->create();
        $near = Incident::factory()->kmFrom($this->centroid, northKm: 0.51)->withCode('A')->create();
        $middle = Incident::factory()->kmFrom($this->centroid, northKm: -1.2)->withCode('B')->create();

        Incident::factory()->kmFrom($this->centroid, northKm: 0.49)->create();
        Incident::factory()->kmFrom($this->centroid, eastKm: 2.01)->create();
        Incident::factory()->at($this->centroid)->create();

        $incidents = $this->neighborhood->incidents;

        $this->assertSame([$near->id, $middle->id, $far->id], $incidents->modelKeys());
        $this->assertSame('ABC', $incidents->map->code->implode(''));
    }

    public function test_it_excludes_incidents_inside_the_bounding_box_but_outside_the_outer_circle(): void
    {
        Incident::factory()->kmFrom($this->centroid, northKm: 1.5, eastKm: 1.5)->create();

        $this->assertTrue($this->neighborhood->incidents->isEmpty());
    }

    public function test_eager_loading_matches_each_neighborhood_to_its_own_donut(): void
    {
        $neighbor = Neighborhood::factory()
            ->centeredAt(new Coordinate($this->centroid->latitude + 3 / Coordinate::KM_PER_DEGREE, $this->centroid->longitude))
            ->create();

        $onlyOurs = Incident::factory()->kmFrom($this->centroid, northKm: -1)->create();
        $shared = Incident::factory()->kmFrom($this->centroid, northKm: 1.5)->create();
        $onlyNeighbors = Incident::factory()->kmFrom($this->centroid, northKm: 4)->create();

        $neighborhoods = Neighborhood::query()->with('incidents')->get()->keyBy('id');

        $this->assertSame([$onlyOurs->id, $shared->id], $neighborhoods[$this->neighborhood->id]->incidents->modelKeys());
        $this->assertSame([$onlyNeighbors->id, $shared->id], $neighborhoods[$neighbor->id]->incidents->modelKeys());
        $this->assertEquals(
            $this->neighborhood->incidents()->get()->modelKeys(),
            $neighborhoods[$this->neighborhood->id]->incidents->modelKeys(),
        );
    }

    public function test_it_supports_existence_and_count_queries(): void
    {
        $empty = Neighborhood::factory()->centeredAt(new Coordinate(33.25, 44.30))->create();

        Incident::factory()->count(2)->kmFrom($this->centroid, northKm: 1)->create();

        $this->assertSame([$this->neighborhood->id], Neighborhood::query()->has('incidents')->pluck('id')->all());
        $this->assertSame(0, Neighborhood::query()->withCount('incidents')->find($empty->id)->incidents_count);
        $this->assertSame(2, Neighborhood::query()->withCount('incidents')->find($this->neighborhood->id)->incidents_count);
    }

    public function test_it_rejects_an_inner_radius_that_is_not_smaller_than_the_outer_radius(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WithinDonut($this->neighborhood, Incident::class, innerRadiusKm: 2.0, outerRadiusKm: 0.5);
    }
}
