<?php

namespace Fapi\Component\Routing;

use Fapi\Component\Routing\Matcher\MatcherInterface;
use Fapi\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fapi\Component\Routing\Matcher
 *
 * Matches Route for the request.
 *
 * @author  Kris Rybak <kris@krisrybak.com>
 */
class Matcher implements MatcherInterface
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RouteCollection
     */
    protected $collection;

    private static $regexOperands = array(
        'int' => '\d+',
        'str' => '\w+',
    );

    /**
     * Matches current Request to Route
     */
    public function match(RouteCollection $collection, Request $request)
    {
        // Save collection and Request
        $this->collection   = $collection;
        $this->request      = $request;

        // Get candidates and ask Voter to decide which candidate mathes best
        $this->candidates   = $this->getCandidates();
    }

    public function getCandidates()
    {
        $candidates = array();

        foreach ($this->collection->all() as $name => $route) {
            $specs = array();

            preg_match_all('/\{\w+\}/', $route->getPath(), $matches);

            if (isset($matches[0])) {
                $specs = $matches[0];
            }

            foreach ($specs as $spec) {
                $param          = substr($spec, 1, -1);
                $regexSpec      = '\\'.$spec.'\\';
                $requirements   = $route->getRequirements();

                if (isset($requirements[$param])) {
                    $route->setRegex(

                        str_replace(
                            $spec,
                            $this->getRegexOperand($requirements[$param]),
                            $route->getRegex()
                        )
                    );
                }
            }

            // Build regeular expression to match routes
            $route->setRegex('^'.'/'.ltrim(trim($route->getRegex()), '/').'/?$');
            $route->setRegex('/'.str_replace('/', '\/', $route->getRegex()).'/');

            if (preg_match($route->getRegex(), $this->request->getPathInfo())) {
                // Check the rquest method matches route methods
                if (in_array($this->request->getMethod(), $route->getMethods())) {

                    // We have a match
                    $candidates[] = $route;
                }
            }
        }

        return $candidates;
    }

    private function getRegexOperand($operand)
    {
        if (array_key_exists($operand, self::$regexOperands)) {
            return self::$regexOperands[$operand];
        }
    }
}
