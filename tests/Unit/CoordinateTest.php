<?php

namespace Tests\Unit;

use App\Support\Geo\Coordinate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoordinateTest extends TestCase
{
    public function test_it_parses_postgres_points_and_polygons_as_latitude_longitude(): void
    {
        [$point] = Coordinate::allFromPostgres('(33.334851004851,44.3525)');
        $polygon = Coordinate::allFromPostgres('((33.32,44.34),(33.325,44.365),(33.34,44.365),(33.34,44.34))');

        $this->assertEquals(new Coordinate(33.334851004851, 44.3525), $point);
        $this->assertCount(4, $polygon);
        $this->assertEquals(new Coordinate(33.325, 44.365), $polygon[1]);
    }

    public function test_it_rejects_unparseable_geometry(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coordinate::allFromPostgres('not a point');
    }

    public function test_the_bounding_box_center_ignores_how_vertices_are_distributed(): void
    {
        $trapezoid = Coordinate::allFromPostgres('((33.32,44.34),(33.325,44.365),(33.34,44.365),(33.34,44.34))');

        $center = Coordinate::boundingBoxCenter($trapezoid);

        $this->assertEqualsWithDelta(33.33, $center->latitude, 1e-12);
        $this->assertEqualsWithDelta(44.3525, $center->longitude, 1e-12);
    }

    public function test_distance_scales_longitude_by_the_cosine_of_the_origin_latitude(): void
    {
        $origin = new Coordinate(60.0, 10.0);

        $this->assertEqualsWithDelta(111.0, $origin->distanceInKmTo(new Coordinate(61.0, 10.0)), 1e-9);
        $this->assertEqualsWithDelta(55.5, $origin->distanceInKmTo(new Coordinate(60.0, 11.0)), 1e-9);
    }
}
