<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Database\Model\Relations;

use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\ModelNotFoundException;
use Hyperf\Stringable\Str;
use Hyperf\Stringable\StrCache;
use InvalidArgumentException;

use function Hyperf\Collection\collect;
use function Hyperf\Support\class_basename;
use function Hyperf\Tappable\tap;

class BelongsToMany extends Relation
{
    use Concerns\InteractsWithPivotTable;

    /**
     * Indicates if timestamps are available on the pivot table.
     *
     * @var bool
     */
    public $withTimestamps = false;

    /**
     * The intermediate table for the relation.
     *
     * @var string
     */
    protected $table;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignPivotKey;

    /**
     * The associated key of the relation.
     *
     * @var string
     */
    protected $relatedPivotKey;

    /**
     * The key name of the parent model.
     *
     * @var string
     */
    protected $parentKey;

    /**
     * The key name of the related model.
     *
     * @var string
     */
    protected $relatedKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The pivot table columns to retrieve.
     *
     * @var array
     */
    protected $pivotColumns = [];

    /**
     * Any pivot table restrictions for where clauses.
     *
     * @var array
     */
    protected $pivotWheres = [];

    /**
     * Any pivot table restrictions for whereIn clauses.
     *
     * @var array
     */
    protected $pivotWhereIns = [];

    /**
     * The default values for the pivot columns.
     *
     * @var array
     */
    protected $pivotValues = [];

    /**
     * The custom pivot table column for the created_at timestamp.
     *
     * @var string
     */
    protected $pivotCreatedAt;

    /**
     * The custom pivot table column for the updated_at timestamp.
     *
     * @var string
     */
    protected $pivotUpdatedAt;

    /**
     * The class name of the custom pivot model to use for the relationship.
     *
     * @var string
     */
    protected $using;

    /**
     * The name of the accessor to use for the "pivot" relationship.
     *
     * @var string
     */
    protected $accessor = 'pivot';

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * Create a new belongs to many relationship instance.
     *
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string $relationName
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        $this->table = $table;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->foreignPivotKey = $foreignPivotKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        $this->performJoin();

        if (Constraint::isConstraint()) {
            $this->addWhereConstraints();
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models)
    {
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);

        $this->query->{$whereIn}(
            $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, $this->parentKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // the parent models. Then we will return the hydrated models back out.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->parentKey}])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Get the class being used for pivot models.
     *
     * @return string
     */
    public function getPivotClass()
    {
        return $this->using ?? Pivot::class;
    }

    /**
     * Specify the custom pivot model to use for the relationship.
     *
     * @param string $class
     * @return $this
     */
    public function using($class)
    {
        $this->using = $class;

        return $this;
    }

    /**
     * Specify the custom pivot accessor to use for the relationship.
     *
     * @param string $accessor
     * @return $this
     */
    public function as($accessor)
    {
        $this->accessor = $accessor;

        return $this;
    }

    /**
     * Set a where clause for a pivot table column.
     *
     * @param string $column
     * @param string $operator
     * @param string $boolean
     * @param null|mixed $value
     * @return $this
     */
    public function wherePivot($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->pivotWheres[] = func_get_args();

        return $this->where($this->table . '.' . $column, $operator, $value, $boolean);
    }

    /**
     * Set a "where in" clause for a pivot table column.
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @param mixed $values
     * @return $this
     */
    public function wherePivotIn($column, $values, $boolean = 'and', $not = false)
    {
        $this->pivotWhereIns[] = func_get_args();

        return $this->whereIn($this->table . '.' . $column, $values, $boolean, $not);
    }

    /**
     * Set an "or where" clause for a pivot table column.
     *
     * @param string $column
     * @param string $operator
     * @param null|mixed $value
     * @return $this
     */
    public function orWherePivot($column, $operator = null, $value = null)
    {
        return $this->wherePivot($column, $operator, $value, 'or');
    }

    /**
     * Set a where clause for a pivot table column.
     *
     * In addition, new pivot records will receive this value.
     *
     * @param array|string $column
     * @param null|mixed $value
     * @return $this
     */
    public function withPivotValue($column, $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $name => $value) {
                $this->withPivotValue($name, $value);
            }

            return $this;
        }

        if (is_null($value)) {
            throw new InvalidArgumentException('The provided value may not be null.');
        }

        $this->pivotValues[] = compact('column', 'value');

        return $this->wherePivot($column, '=', $value);
    }

    /**
     * Set an "or where in" clause for a pivot table column.
     *
     * @param string $column
     * @param mixed $values
     * @return $this
     */
    public function orWherePivotIn($column, $values)
    {
        return $this->wherePivotIn($column, $values, 'or');
    }

    /**
     * Find a related model by its primary key or return new instance of the related model.
     *
     * @param array $columns
     * @param mixed $id
     * @return \Hyperf\Collection\Collection|Model
     */
    public function findOrNew($id, $columns = ['*'])
    {
        if (is_null($instance = $this->find($id, $columns))) {
            $instance = $this->related->newInstance();
        }

        return $instance;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return Model
     */
    public function firstOrNew(array $attributes)
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->related->newInstance($attributes);
        }

        return $instance;
    }

    /**
     * Get the first related record matching the attributes. If the record is not found, create it.
     *
     * @param bool $touch
     * @return Model
     */
    public function firstOrCreate(array $attributes, array $joining = [], $touch = true)
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create($attributes, $joining, $touch);
        }

        return $instance;
    }

    /**
     * Attempt to create the record. If a unique constraint violation occurs, attempt to find the matching record.
     *
     * @param bool $touch
     * @return Model
     */
    public function createOrFirst(array $attributes = [], array $values = [], array $joining = [], $touch = true)
    {
        try {
            return $this->create(array_merge($attributes, $values), $joining, $touch);
        } catch (UniqueConstraintViolationException $exception) {
            // ...
        }

        try {
            return tap($this->related->where($attributes)->first(), function ($instance) use ($joining, $touch) {
                $this->attach($instance, $joining, $touch);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return (clone $this)->where($attributes)->first();
        }
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @param bool $touch
     * @return Model
     */
    public function updateOrCreate(array $attributes, array $values = [], array $joining = [], $touch = true)
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            return $this->create($values, $joining, $touch);
        }

        $instance->fill($values);

        $instance->save(['touch' => false]);

        return $instance;
    }

    /**
     * Find a related model by its primary key.
     *
     * @param array $columns
     * @param mixed $id
     * @return null|Collection|Model
     */
    public function find($id, $columns = ['*'])
    {
        return is_array($id) ? $this->findMany($id, $columns) : $this->where(
            $this->getRelated()->getQualifiedKeyName(),
            '=',
            $id
        )->first($columns);
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * @param array $columns
     * @param mixed $ids
     * @return Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        return empty($ids) ? $this->getRelated()->newCollection() : $this->whereIn(
            $this->getRelated()->getQualifiedKeyName(),
            $ids
        )->get($columns);
    }

    /**
     * Find a related model by its primary key or throw an exception.
     *
     * @param array $columns
     * @param mixed $id
     * @return Collection|Model
     * @throws ModelNotFoundException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);

        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (! is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException())->setModel(get_class($this->related), $id);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array $columns
     */
    public function first($columns = ['*'])
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? $results->first() : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param array $columns
     * @return Model|static
     * @throws ModelNotFoundException
     */
    public function firstOrFail($columns = ['*'])
    {
        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        throw (new ModelNotFoundException())->setModel(get_class($this->related));
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return Collection
     */
    public function get($columns = ['*'])
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $builder = $this->query->applyScopes();

        $columns = $builder->getQuery()->columns ? [] : $columns;

        $models = $builder->addSelect(
            $this->shouldSelect($columns)
        )->getModels();

        $this->hydratePivotRelation($models);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get a paginator for the "select" statement.
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginatorInterface
    {
        $this->query->addSelect($this->shouldSelect($columns));

        return tap($this->query->paginate($perPage, $columns, $pageName, $page), function ($paginator) {
            $this->hydratePivotRelation($paginator->items());
        });
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param null|int $page
     * @return PaginatorInterface
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $this->query->addSelect($this->shouldSelect($columns));

        return tap($this->query->simplePaginate($perPage, $columns, $pageName, $page), function ($paginator) {
            $this->hydratePivotRelation($paginator->items());
        });
    }

    /**
     * Chunk the results of the query.
     *
     * @param int $count
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        $this->query->addSelect($this->shouldSelect());

        return $this->query->chunk($count, function ($results) use ($callback) {
            $this->hydratePivotRelation($results->all());

            return $callback($results);
        });
    }

    /**
     * Execute a callback over each item while chunking.
     *
     * @param int $count
     * @return bool
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * If we're touching the parent model, touch.
     */
    public function touchIfTouching()
    {
        if ($this->touchingParent()) {
            $this->getParent()->touch();
        }

        if ($this->getParent()->touches($this->relationName)) {
            $this->touch();
        }
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * E.g.: Touch all roles associated with this user.
     */
    public function touch()
    {
        $key = $this->getRelated()->getKeyName();

        $columns = [
            $this->related->getUpdatedAtColumn() => $this->related->freshTimestampString(),
        ];

        // If we actually have IDs for the relation, we will run the query to update all
        // the related model's timestamps, to make sure these all reflect the changes
        // to the parent models. This will help us keep any caching synced up here.
        if (count($ids = $this->allRelatedIds()) > 0) {
            $this->getRelated()->newModelQuery()->whereIn($key, $ids)->update($columns);
        }
    }

    /**
     * Get all of the IDs for the related models.
     *
     * @return \Hyperf\Collection\Collection
     */
    public function allRelatedIds()
    {
        return $this->newPivotQuery()->pluck($this->relatedPivotKey);
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param bool $touch
     * @return Model
     */
    public function save(Model $model, array $pivotAttributes = [], $touch = true)
    {
        $model->save(['touch' => false]);

        $this->attach($model, $pivotAttributes, $touch);

        return $model;
    }

    /**
     * Save an array of new models and attach them to the parent model.
     *
     * @param array|\Hyperf\Collection\Collection $models
     * @return array
     */
    public function saveMany($models, array $pivotAttributes = [])
    {
        foreach ($models as $key => $model) {
            $this->save($model, (array) ($pivotAttributes[$key] ?? []), false);
        }

        $this->touchIfTouching();

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param bool $touch
     * @return Model
     */
    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $instance = $this->related->newInstance($attributes);

        // Once we save the related model, we need to attach it to the base model via
        // through intermediate table so we'll use the existing "attach" method to
        // accomplish this which will insert the record and any more attributes.
        $instance->save(['touch' => false]);

        $this->attach($instance, $joining, $touch);

        return $instance;
    }

    /**
     * Create an array of new instances of the related models.
     *
     * @return array
     */
    public function createMany(array $records, array $joinings = [])
    {
        $instances = [];

        foreach ($records as $key => $record) {
            $instances[] = $this->create($record, (array) ($joinings[$key] ?? []), false);
        }

        $this->touchIfTouching();

        return $instances;
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param array|mixed $columns
     * @return Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param array|mixed $columns
     * @return Builder
     */
    public function getRelationExistenceQueryForSelfJoin(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->select($columns);

        $query->from($this->related->getTable() . ' as ' . $hash = $this->getRelationCountHash());

        $this->related->setTable($hash);

        $this->performJoin($query);

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getExistenceCompareKey()
    {
        return $this->getQualifiedForeignPivotKeyName();
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param null|mixed $createdAt
     * @param null|mixed $updatedAt
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        $this->withTimestamps = true;

        $this->pivotCreatedAt = $createdAt;
        $this->pivotUpdatedAt = $updatedAt;

        return $this->withPivot($this->createdAt(), $this->updatedAt());
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->pivotCreatedAt ?: $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->pivotUpdatedAt ?: $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the foreign key for the relation.
     *
     * @return string
     */
    public function getForeignPivotKeyName()
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the fully qualified foreign key for the relation.
     *
     * @return string
     */
    public function getQualifiedForeignPivotKeyName()
    {
        return $this->table . '.' . $this->foreignPivotKey;
    }

    /**
     * Get the "related key" for the relation.
     *
     * @return string
     */
    public function getRelatedPivotKeyName()
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the fully qualified "related key" for the relation.
     *
     * @return string
     */
    public function getQualifiedRelatedPivotKeyName()
    {
        return $this->table . '.' . $this->relatedPivotKey;
    }

    /**
     * Get the parent key for the relationship.
     *
     * @return string
     */
    public function getParentKeyName()
    {
        return $this->parentKey;
    }

    /**
     * Get the fully qualified parent key name for the relation.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->qualifyColumn($this->parentKey);
    }

    /**
     * Get the related key for the relationship.
     *
     * @return string
     */
    public function getRelatedKeyName()
    {
        return $this->relatedKey;
    }

    /**
     * Get the intermediate table for the relationship.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the relationship name for the relationship.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the name of the pivot accessor for this relationship.
     *
     * @return string
     */
    public function getPivotAccessor()
    {
        return $this->accessor;
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param null|Builder $query
     * @return $this
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $baseTable = $this->related->getTable();

        $key = $baseTable . '.' . $this->relatedKey;

        $query->join($this->table, $key, '=', $this->getQualifiedRelatedPivotKeyName());

        return $this;
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            $this->parent->{$this->parentKey}
        );

        return $this;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->accessor}->{$this->foreignPivotKey}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the select columns for the relation query.
     *
     * @return array
     */
    protected function shouldSelect(array $columns = ['*'])
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable() . '.*'];
        }

        return array_merge($columns, $this->aliasedPivotColumns());
    }

    /**
     * Get the pivot columns for the relation.
     *
     * "pivot_" is prefixed ot each column for easy removal later.
     *
     * @return array
     */
    protected function aliasedPivotColumns()
    {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey];

        return collect(array_merge($defaults, $this->pivotColumns))->map(function ($column) {
            return $this->table . '.' . $column . ' as pivot_' . $column;
        })->unique()->all();
    }

    /**
     * Hydrate the pivot table relationship on the models.
     */
    protected function hydratePivotRelation(array $models)
    {
        // To hydrate the pivot relationship, we will just gather the pivot attributes
        // and create a new Pivot model, which is basically a dynamic model that we
        // will set the attributes, table, and connections on it so it will work.
        foreach ($models as $model) {
            $model->setRelation($this->accessor, $this->newExistingPivot(
                $this->migratePivotAttributes($model)
            ));
        }
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @return array
     */
    protected function migratePivotAttributes(Model $model)
    {
        $values = [];

        foreach ($model->getAttributes() as $key => $value) {
            // To get the pivots attributes we will just take any of the attributes which
            // begin with "pivot_" and add those to this arrays, as well as unsetting
            // them from the parent's models since they exist in a different table.
            if (strpos($key, 'pivot_') === 0) {
                $values[substr($key, 6)] = $value;

                unset($model->{$key});
            }
        }

        return $values;
    }

    /**
     * Determine if we should touch the parent on sync.
     *
     * @return bool
     */
    protected function touchingParent()
    {
        return $this->getRelated()->touches($this->guessInverseRelation());
    }

    /**
     * Attempt to guess the name of the inverse of the relation.
     *
     * @return string
     */
    protected function guessInverseRelation()
    {
        return StrCache::camel(Str::pluralStudly(class_basename($this->getParent())));
    }
}
