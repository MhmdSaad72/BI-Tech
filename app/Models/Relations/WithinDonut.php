<?php

namespace App\Models\Relations;

use App\Support\Geo\Coordinate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Relates a parent with a centroid to every related model whose coordinate
 * lies in the annulus (donut) between two radii around that centroid,
 * ordered from the nearest to the farthest.
 *
 * Bounds are inclusive on both radii. Comparisons are done on squared
 * distances so the base query needs no square root.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends Relation<TRelatedModel, TDeclaringModel, EloquentCollection<int, TRelatedModel>>
 */
class WithinDonut extends Relation
{
    /**
     * @param  TDeclaringModel  $parent
     * @param  class-string<TRelatedModel>  $related
     */
    public function __construct(
        Model $parent,
        string $related,
        protected float $innerRadiusKm,
        protected float $outerRadiusKm,
        protected string $centroidLatitudeColumn = 'centroid_latitude',
        protected string $centroidLongitudeColumn = 'centroid_longitude',
        protected string $latitudeColumn = 'latitude',
        protected string $longitudeColumn = 'longitude',
    ) {
        if ($innerRadiusKm < 0 || $innerRadiusKm >= $outerRadiusKm) {
            throw new InvalidArgumentException("Invalid donut radii [{$innerRadiusKm} km, {$outerRadiusKm} km].");
        }

        parent::__construct((new $related)->newQuery(), $parent);
    }

    public function addConstraints(): void
    {
        if (! static::$constraints) {
            return;
        }

        $centroid = $this->centroidOf($this->parent);

        if ($centroid === null) {
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->whereWithinBoundingBox($this->query, $centroid);
        $this->whereWithinDonut($this->query, $centroid);
        $this->orderByDistanceFrom($this->query, $centroid);
    }

    /**
     * Fetch candidates inside the bounding box of every parent's outer circle;
     * the exact donut test and ordering happen per parent in match().
     *
     * @param  array<int, TDeclaringModel>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        $centroids = array_filter(array_map($this->centroidOf(...), $models));

        if ($centroids === []) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $this->query->where(function (Builder $query) use ($centroids): void {
            foreach ($centroids as $centroid) {
                $query->orWhere(fn (Builder $query) => $this->whereWithinBoundingBox($query, $centroid));
            }
        });
    }

    /**
     * @param  array<int, TDeclaringModel>  $models
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * @param  array<int, TDeclaringModel>  $models
     * @param  EloquentCollection<int, TRelatedModel>  $results
     * @param  string  $relation
     * @return array<int, TDeclaringModel>
     */
    public function match(array $models, EloquentCollection $results, $relation): array
    {
        foreach ($models as $model) {
            $centroid = $this->centroidOf($model);

            if ($centroid === null) {
                continue;
            }

            $matches = $results
                ->map(fn (Model $result): array => [$result, $centroid->squaredDistanceInKmTo($this->coordinateOf($result))])
                ->filter(fn (array $pair): bool => $pair[1] >= $this->innerRadiusKm ** 2 && $pair[1] <= $this->outerRadiusKm ** 2)
                ->sort(fn (array $a, array $b): int => [$a[1], $a[0]->getKey()] <=> [$b[1], $b[0]->getKey()])
                ->map(fn (array $pair): Model => $pair[0])
                ->values()
                ->all();

            $model->setRelation($relation, $this->related->newCollection($matches));
        }

        return $models;
    }

    /**
     * @return EloquentCollection<int, TRelatedModel>
     */
    public function getResults(): EloquentCollection
    {
        return $this->centroidOf($this->parent) === null
            ? $this->related->newCollection()
            : $this->query->get();
    }

    /**
     * Support has(), whereHas() and withCount() with a correlated subquery
     * that reads the centroid from the parent row.
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return Builder<TRelatedModel>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        $grammar = $query->getQuery()->getGrammar();

        $latitude = $grammar->wrap($this->related->qualifyColumn($this->latitudeColumn));
        $longitude = $grammar->wrap($this->related->qualifyColumn($this->longitudeColumn));
        $centroidLatitude = $grammar->wrap($this->parent->qualifyColumn($this->centroidLatitudeColumn));
        $centroidLongitude = $grammar->wrap($this->parent->qualifyColumn($this->centroidLongitudeColumn));

        $kmPerDegree = $this->literal(Coordinate::KM_PER_DEGREE);
        $latitudeKm = "(({$latitude} - {$centroidLatitude}) * {$kmPerDegree})";
        $longitudeKm = "(({$longitude} - {$centroidLongitude}) * {$kmPerDegree} * cos(radians({$centroidLatitude})))";

        return $query->select($columns)->whereRaw(sprintf(
            '%s between %s and %s',
            "{$latitudeKm} * {$latitudeKm} + {$longitudeKm} * {$longitudeKm}",
            $this->literal($this->innerRadiusKm ** 2),
            $this->literal($this->outerRadiusKm ** 2),
        ));
    }

    /**
     * Cheap, index-friendly pre-filter: the square that encloses the outer circle.
     *
     * @param  Builder<TRelatedModel>  $query
     */
    protected function whereWithinBoundingBox(Builder $query, Coordinate $centroid): void
    {
        $latitudeDelta = $this->outerRadiusKm / Coordinate::KM_PER_DEGREE;
        $longitudeDelta = $this->outerRadiusKm / $centroid->kmPerDegreeOfLongitude();

        $query
            ->whereBetween($this->related->qualifyColumn($this->latitudeColumn), [$centroid->latitude - $latitudeDelta, $centroid->latitude + $latitudeDelta])
            ->whereBetween($this->related->qualifyColumn($this->longitudeColumn), [$centroid->longitude - $longitudeDelta, $centroid->longitude + $longitudeDelta]);
    }

    /**
     * @param  Builder<TRelatedModel>  $query
     */
    protected function whereWithinDonut(Builder $query, Coordinate $centroid): void
    {
        $query->whereRaw(sprintf(
            '%s between %s and %s',
            $this->squaredDistanceExpression($query, $centroid),
            $this->literal($this->innerRadiusKm ** 2),
            $this->literal($this->outerRadiusKm ** 2),
        ));
    }

    /**
     * @param  Builder<TRelatedModel>  $query
     */
    protected function orderByDistanceFrom(Builder $query, Coordinate $centroid): void
    {
        $query
            ->orderByRaw($this->squaredDistanceExpression($query, $centroid))
            ->orderBy($this->related->getQualifiedKeyName());
    }

    /**
     * @param  Builder<TRelatedModel>  $query
     */
    protected function squaredDistanceExpression(Builder $query, Coordinate $centroid): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $latitude = $grammar->wrap($this->related->qualifyColumn($this->latitudeColumn));
        $longitude = $grammar->wrap($this->related->qualifyColumn($this->longitudeColumn));

        $latitudeKm = sprintf('((%s - %s) * %s)', $latitude, $this->literal($centroid->latitude), $this->literal(Coordinate::KM_PER_DEGREE));
        $longitudeKm = sprintf('((%s - %s) * %s)', $longitude, $this->literal($centroid->longitude), $this->literal($centroid->kmPerDegreeOfLongitude()));

        return "{$latitudeKm} * {$latitudeKm} + {$longitudeKm} * {$longitudeKm}";
    }

    /**
     * PDO binds floats as strings, and SQLite compares an untyped expression
     * against a string operand as "number < text", so a bound radius would
     * silently match nothing. Floats are therefore inlined as numeric literals.
     */
    protected function literal(float $value): string
    {
        return var_export($value, true);
    }

    protected function centroidOf(Model $model): ?Coordinate
    {
        $latitude = $model->getAttribute($this->centroidLatitudeColumn);
        $longitude = $model->getAttribute($this->centroidLongitudeColumn);

        return $latitude === null || $longitude === null
            ? null
            : new Coordinate((float) $latitude, (float) $longitude);
    }

    protected function coordinateOf(Model $model): Coordinate
    {
        return new Coordinate(
            (float) $model->getAttribute($this->latitudeColumn),
            (float) $model->getAttribute($this->longitudeColumn),
        );
    }
}
