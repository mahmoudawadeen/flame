<?php

namespace Igniter\Flame\Router;

use Igniter\Flame\Support\RouterHelper;
use InvalidArgumentException;

/**
 * Router Rule Object
 *
 * Adapted from october\rain\router\Rule
 */
class Rule
{
    /**
     * @var string A named reference for this rule.
     */
    protected $ruleName;

    /**
     * @var string The pattern used to match this rule.
     */
    protected $rulePattern;

    /**
     * @var Closure Custom condition used when matching this rule.
     */
    protected $conditionCallback;

    /**
     * @var Closure Called when this rule is matched.
     */
    protected $afterMatchCallback;

    /**
     * @var string URL with static segments only, dynamic segments are stripped
     */
    public $staticUrl;

    /**
     * @var array Pattern segments
     */
    public $segments;

    /**
     * @var int The number of static segments found in the pattern
     */
    public $staticSegmentCount = 0;

    /**
     * @var int The number of dynamic segments found in the pattern
     */
    public $dynamicSegmentCount = 0;

    /**
     * @var int The number of wildcard segments found in the pattern
     */
    public $wildSegmentCount = 0;

    /**
     * Creates a new router rule instance.
     *
     * @param string $name
     * @param string $pattern
     */
    public function __construct($name, $pattern)
    {
        $this->ruleName = $name;
        $this->rulePattern = $pattern;
        $this->segments = RouterHelper::segmentizeUrl($pattern);

        /*
         * Create the static URL for this pattern
         */
        $staticSegments = [];
        foreach ($this->segments as $segment) {
            if (strpos($segment, ':') !== 0) {
                $staticSegments[] = $segment;
                $this->staticSegmentCount++;
            }
            else {
                $this->dynamicSegmentCount++;

                if (RouterHelper::segmentIsWildcard($segment)) {
                    $this->wildSegmentCount++;
                }
            }
        }

        $this->staticUrl = RouterHelper::rebuildUrl($staticSegments);
    }

    /**
     * Checks whether a given URL matches a given pattern.
     * @param string $url The URL to check.
     * @param array $parameters A reference to a PHP array variable to return the parameter list fetched from URL.
     * @return bool Returns true if the URL matches the pattern. Otherwise returns false.
     */
    public function resolveUrl($url, &$parameters)
    {
        $parameters = [];

        $patternSegments = $this->segments;
        $patternSegmentNum = count($patternSegments);
        $urlSegments = RouterHelper::segmentizeUrl($url);

        /*
         * Only one wildcard can be used, if found, pull out the excess segments
         */
        if ($this->wildSegmentCount === 1) {
            $wildSegments = $this->captureWildcardSegments($urlSegments);
        }

        /*
         * If the number of URL segments is more than the number of pattern segments - return false
         */
        if (count($urlSegments) > count($patternSegments)) {
            return FALSE;
        }

        /*
         * Compare pattern and URL segments
         */
        foreach ($patternSegments as $index => $patternSegment) {
            $patternSegmentLower = mb_strtolower($patternSegment);

            if (strpos($patternSegment, ':') !== 0) {
                /*
                 * Static segment
                 */
                if (
                    !array_key_exists($index, $urlSegments) ||
                    $patternSegmentLower != mb_strtolower($urlSegments[$index])
                ) {
                    return FALSE;
                }
            }
            else {
                /*
                 * Dynamic segment. Initialize the parameter
                 */
                $paramName = RouterHelper::getParameterName($patternSegment);
                $parameters[$paramName] = FALSE;

                /*
                 * Determine whether it is optional
                 */
                $optional = RouterHelper::segmentIsOptional($patternSegment);

                /*
                 * Check if the optional segment has no required segments following it
                 */
                if ($optional && $index < $patternSegmentNum - 1) {
                    for ($i = $index + 1; $i < $patternSegmentNum; $i++) {
                        if (!RouterHelper::segmentIsOptional($patternSegments[$i])) {
                            $optional = FALSE;
                            break;
                        }
                    }
                }

                /*
                 * If the segment is optional and there is no corresponding value in the URL, assign the default value (if provided)
                 * and skip to the next segment.
                 */
                $urlSegmentExists = array_key_exists($index, $urlSegments);

                if ($optional && !$urlSegmentExists) {
                    $parameters[$paramName] = RouterHelper::getSegmentDefaultValue($patternSegment);
                    continue;
                }

                /*
                 * If the segment is not optional and there is no corresponding value in the URL, return false
                 */
                if (!$optional && !$urlSegmentExists) {
                    return FALSE;
                }

                /*
                 * Validate the value with the regular expression
                 */
                $regexp = RouterHelper::getSegmentRegExp($patternSegment);

                if ($regexp) {
                    try {
                        if (!preg_match($regexp, $urlSegments[$index])) {
                            return FALSE;
                        }
                    }
                    catch (\Exception $ex) {
                    }
                }

                /*
                 * Set the parameter value
                 */
                $parameters[$paramName] = $urlSegments[$index];

                /*
                 * Determine if wildcard and add stored parameters as a suffix
                 */
                if (RouterHelper::segmentIsWildcard($patternSegment) && count($wildSegments)) {
                    $parameters[$paramName] .= RouterHelper::rebuildUrl($wildSegments);
                }
            }
        }

        return TRUE;
    }

    /**
     * Captures and removes every segment of a URL after a wildcard
     * pattern segment is detected, until both collections of segments
     * are the same size.
     * @param array $urlSegments
     * @return array
     */
    protected function captureWildcardSegments(&$urlSegments)
    {
        $wildSegments = [];
        $patternSegments = $this->segments;
        $segmentDiff = count($urlSegments) - count($patternSegments);
        $wildMode = FALSE;
        $wildCount = 0;

        foreach ($urlSegments as $index => $urlSegment) {
            if ($wildMode) {
                if ($wildCount < $segmentDiff) {
                    $wildSegments[] = $urlSegment;
                    $wildCount++;
                    unset($urlSegments[$index]);
                    continue;
                }

                break;
            }

            $patternSegment = $patternSegments[$index];
            if (RouterHelper::segmentIsWildcard($patternSegment)) {
                $wildMode = TRUE;
            }
        }

        // Reset array index
        $urlSegments = array_values($urlSegments);

        return $wildSegments;
    }

    /**
     * Unique route name
     *
     * @param string $name Unique name for the router object
     * @return object Self
     */
    public function name($name = null)
    {
        if ($name === null) {
            return $this->ruleName;
        }

        $this->ruleName = $name;

        return $this;
    }

    /**
     * Route match pattern
     *
     * @param string $pattern Pattern used to match this rule
     * @return object Self
     */
    public function pattern($pattern = null)
    {
        if ($pattern === null) {
            return $this->rulePattern;
        }

        $this->rulePattern = $pattern;

        return $this;
    }

    /**
     * Condition callback
     *
     * @param callable $callback Callback function to be used when providing custom route match conditions
     * @return callable
     * @throws InvalidArgumentException When supplied argument is not a valid callback
     */
    public function condition($callback = null)
    {
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(sprintf(
                    'Condition provided is not a valid callback. Given (%s)',
                    gettype($callback)
                ));
            }

            $this->conditionCallback = $callback;

            return $this;
        }

        return $this->conditionCallback;
    }

    /**
     * After match callback
     *
     * @param callable $callback Callback function to be used to modify params after a successful match
     * @return callable
     * @throws InvalidArgumentException When supplied argument is not a valid callback
     */
    public function afterMatch($callback = null)
    {
        if ($callback !== null) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(sprintf(
                    'The after match callback provided is not valid. Given (%s)',
                    gettype($callback)
                ));
            }

            $this->afterMatchCallback = $callback;

            return $this;
        }

        return $this->afterMatchCallback;
    }
}
