<?php

namespace Igniter\Flame\Database\Query;

use Igniter\Flame\Database\MemoryCache;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

/**
 * Adapted from october\rain\database\QueryBuilder
 */
class Builder extends IlluminateQueryBuilder
{
    /**
     * The key that should be used when caching the query.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * The number of minutes to cache the query.
     *
     * @var int
     */
    protected $cacheMinutes;

    /**
     * The tags for the query cache.
     *
     * @var array
     */
    protected $cacheTags;

    /**
     * Indicates whether duplicate queries are being cached in memory.
     *
     * @var bool
     */
    protected $cachingDuplicateQueries = FALSE;

    /**
     * The aliased concatenation columns.
     *
     * @var array
     */
    public $concats = [];

    /**
     * Get an array with the values of a given column.
     *
     * @param string $column
     * @param string|null $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        return $this->pluck($column, $key)->all();
    }

    /**
     * Indicate that the query results should be cached.
     *
     * @param \DateTime|int $minutes
     * @param string $key
     * @return $this
     */
    public function remember($minutes, $key = null)
    {
        $this->cacheMinutes = $minutes;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * Indicate that the query results should be cached forever.
     *
     * @param string $key
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function rememberForever($key = null)
    {
        return $this->remember(-1, $key);
    }

    /**
     * Indicate that the results, if cached, should use the given cache tags.
     *
     * @param array|mixed $cacheTags
     * @return $this
     */
    public function cacheTags($cacheTags)
    {
        $this->cacheTags = $cacheTags;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get($columns = ['*'])
    {
        if ($this->cachingDuplicates()) {
            return $this->getDuplicateCached($columns);
        }

        if (!is_null($this->cacheMinutes)) {
            return $this->getCached($columns);
        }

        return parent::get($columns);
    }

    /**
     * Check the memory cache before executing the query
     *
     * @param array $columns
     * @return array
     */
    protected function getDuplicateCached($columns = ['*'])
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        $cache = MemoryCache::instance();

        if ($cache->has($this)) {
            $results = collect($cache->get($this));
        }
        else {
            $results = !is_null($this->cacheMinutes)
                ? $this->getCached($columns)
                : parent::get($columns);

            $cache->put($this, $results->all());
        }

        return $results;
    }

    /**
     * Execute the query as a cached "select" statement.
     *
     * @param array $columns
     * @return array
     */
    public function getCached($columns = ['*'])
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        // If the query is requested to be cached, we will cache it using a unique key
        // for this database connection and query statement, including the bindings
        // that are used on this query, providing great convenience when caching.
        [$key, $minutes] = $this->getCacheInfo();

        $cache = $this->getCache();

        $callback = $this->getCacheCallback($columns);

        // If the "minutes" value is less than zero, we will use that as the indicator
        // that the value should be remembered values should be stored indefinitely
        // and if we have minutes we will use the typical remember function here.
        if (is_int($minutes) && $minutes < 0) {
            $results = $cache->rememberForever($key, $callback);
        }
        else {
            if (is_int($minutes)) {
                $expiresAt = now()->addMinutes($minutes);
            }
            else {
                $expiresAt = $minutes;
            }
            $results = $cache->remember($key, $expiresAt, $callback);
        }

        return collect($results);
    }

    /**
     * Get the cache object with tags assigned, if applicable.
     *
     * @return \Illuminate\Cache\CacheManager
     */
    protected function getCache()
    {
        $cache = App::make('cache');

        return $this->cacheTags ? $cache->tags($this->cacheTags) : $cache;
    }

    /**
     * Get the cache key and cache minutes as an array.
     *
     * @return array
     */
    protected function getCacheInfo()
    {
        return [$this->getCacheKey(), $this->cacheMinutes];
    }

    /**
     * Get a unique cache key for the complete query.
     *
     * @return string
     */
    public function getCacheKey()
    {
        return $this->cacheKey ?: $this->generateCacheKey();
    }

    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $name = $this->connection->getName();

        return md5($name.$this->toSql().serialize($this->getBindings()));
    }

    /**
     * Get the Closure callback used when caching queries.
     *
     * @param array $columns
     * @return \Closure
     */
    protected function getCacheCallback($columns)
    {
        return function () use ($columns) {
            return parent::get($columns)->all();
        };
    }

    /**
     * Retrieve the "count" result of the query,
     * also strips off any orderBy clause.
     *
     * @param string $columns
     * @return int
     */
    public function count($columns = '*')
    {
        $previousOrders = $this->orders;

        $this->orders = null;

        $result = parent::count($columns);

        $this->orders = $previousOrders;

        return $result;
    }

    /**
     * Update a record in the database.
     *
     * @param array $values
     * @return int
     */
    public function update(array $values)
    {
        $this->clearDuplicateCache();

        return parent::update($values);
    }

    /**
     * Delete a record from the database.
     *
     * @param mixed $id
     * @return int
     */
    public function delete($id = null)
    {
        $this->clearDuplicateCache();

        return parent::delete($id);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param array $values
     * @param string $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->clearDuplicateCache();

        return parent::insertGetId($values, $sequence);
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     * @return bool
     */
    public function insert(array $values)
    {
        $this->clearDuplicateCache();

        return parent::insert($values);
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array $values
     * @param array|string $uniqueBy
     * @param array|null $update
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        }

        if ($update === []) {
            return (int)$this->insert($values);
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $bindings = $this->cleanBindings(array_merge(
            Arr::flatten($values, 1),
            collect($update)->reject(function ($value, $key) {
                return is_int($key);
            })->all()
        ));

        return $this->connection->affectingStatement(
            $this->grammar->compileUpsert($this, $values, (array)$uniqueBy, $update),
            $bindings
        );
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        $this->clearDuplicateCache();

        parent::truncate();
    }

    /**
     * Clear memory cache for the given table.
     *
     * @param string|null $table
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function clearDuplicateCache($table = null)
    {
        MemoryCache::instance()->forget($table ?: $this->from);

        return $this;
    }

    /**
     * Flush the memory cache.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function flushDuplicateCache()
    {
        MemoryCache::instance()->flush();

        return $this;
    }

    /**
     * Enable the memory cache on the query.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function enableDuplicateCache()
    {
        $this->cachingDuplicateQueries = TRUE;

        return $this;
    }

    /**
     * Disable the memory cache on the query.
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function disableDuplicateCache()
    {
        $this->cachingDuplicateQueries = FALSE;

        return $this;
    }

    /**
     * Determine whether we're caching duplicate queries.
     *
     * @return bool
     */
    public function cachingDuplicates()
    {
        return $this->cachingDuplicateQueries;
    }

    /**
     * Adds a concatenated column as an alias.
     *
     * @param array $parts The concatenation parts.
     * @param string $as The name of the alias for the compiled concatenation.
     * @return $this
     */
    public function selectConcat(array $parts, string $as)
    {
        $this->concats[$as] = $parts;

        return $this;
    }
}
