<?php

namespace Igniter\Flame\Database;

use Igniter\Flame\Database\Query\Builder as QueryBuilder;
use Igniter\Flame\Traits\Singleton;

/**
 * Query memory cache class.
 *
 * Stores query results in memory to avoid running duplicate queries
 *
 * Adapted from october\rain\database\MemoryCache
 */
class MemoryCache
{
    use Singleton;

    /**
     * Cached results.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * The mapper between hashed keys and table names.
     *
     * @var array
     */
    protected $tableMap = [];

    /**
     * @var bool Store enabled state.
     */
    protected $enabled = TRUE;

    /**
     * Check if the memory cache is enabled.
     *
     * @return bool
     */
    public function enabled($switch = null)
    {
        if ($switch !== null) {
            $this->enabled = $switch;
        }

        return $this->enabled;
    }

    /**
     * Check if the given query is cached.
     *
     * @param QueryBuilder $query
     * @return bool
     */
    public function has(QueryBuilder $query)
    {
        return $this->enabled && isset($this->cache[$this->hash($query)]);
    }

    /**
     * Get the cached results for the given query.
     *
     * @param QueryBuilder $query
     * @return array|null
     */
    public function get(QueryBuilder $query)
    {
        if ($this->has($query)) {
            return $this->cache[$this->hash($query)];
        }

        return null;
    }

    /**
     * Store the results for the given query.
     *
     * @param QueryBuilder $query
     * @param array $results
     * @return void
     */
    public function put(QueryBuilder $query, array $results)
    {
        if (!$this->enabled) {
            return;
        }

        $hash = $this->hash($query);

        $this->cache[$hash] = $results;

        $this->tableMap[(string)$query->from][] = $hash;
    }

    /**
     * Delete the cache for the given table.
     *
     * @param string $table
     * @return void
     */
    public function forget($table)
    {
        if (!isset($this->tableMap[$table])) {
            return;
        }

        foreach ($this->tableMap[$table] as $hash) {
            unset($this->cache[$hash]);
        }

        unset($this->tableMap[$table]);
    }

    /**
     * Clear the memory cache.
     * @return void
     */
    public function flush()
    {
        $this->cache = [];
        $this->tableMap = [];
    }

    /**
     * Calculate a hash key for the given query.
     *
     * @param QueryBuilder $query
     * @return string
     */
    protected function hash(QueryBuilder $query)
    {
        // First we will cast all bindings to string, so we can ensure the same
        // hash format regardless of the binding type provided by the user.
        $bindings = array_map(function ($binding) {
            return (string)$binding;
        }, $query->getBindings());

        $name = $query->getConnection()->getName();

        return md5($name.$query->toSql().serialize($bindings));
    }
}
