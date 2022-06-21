<?php

namespace Igniter\Flame\Pagic\Source;

use Symfony\Component\Yaml\Yaml;

abstract class AbstractSource
{
    /**
     * The query post processor implementation.
     *
     * @var \Igniter\Flame\Pagic\Processors\Processor
     */
    protected $processor;

    protected $manifestCache;

    /**
     * Get the query post processor used by the connection.
     * @return \Igniter\Flame\Pagic\Processors\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Generate a cache key unique to this source.
     *
     * @param string $name
     *
     * @return int|string
     */
    public function makeCacheKey($name = '')
    {
        return crc32($name);
    }

    public function getManifest()
    {
        if ($this->manifestCache)
            return $this->manifestCache;

        $path = $this->basePath.'/_meta/pages.yml';
        if (!$this->files->exists($path))
            return [];

        return $this->manifestCache = Yaml::parse($this->files->get($path));
    }
}