<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework;

use GuzzleHttp\Psr7\ServerRequest;
use Medienreaktor\NeosApi\Framework\OpenApiRoutesProvider;
use Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures\GreetingApi;
use Neos\Flow\Mvc\Routing\Dto\RouteContext;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Route;
use Neos\Flow\Tests\UnitTestCase;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;

/**
 * Routing is what makes a generated operation reachable at all, and what puts
 * it in ./flow routing:list - so these pin the translation from an operation
 * to the Flow route that fronts it.
 */
class OpenApiRoutesProviderTest extends UnitTestCase
{
    /**
     * @var array<string, Route>
     */
    private array $routes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $compiled = (new ApiCompiler())->compile(
            ApiDefinition::create(
                info: new InfoObject('Neos API', '1.9'),
                securitySchemes: SecuritySchemeOrReferenceObjectMap::create()->with('oauth2', SecuritySchemeObject::bearer()),
            )->withOperationsFrom(GreetingApi::class, tag: 'Greetings')
        );

        // Buffered because building a Flow Route emits a PHP 8.5 deprecation
        // from Flow itself, which this suite treats as a failing test.
        ob_start();
        $routes = iterator_to_array(new OpenApiRoutesProvider($compiled)->getRoutes());
        ob_end_clean();

        foreach ($routes as $route) {
            $this->routes[$route->getUriPattern()] = $route;
        }
    }

    /**
     * @test
     */
    public function everyOperationBecomesOneRoute(): void
    {
        self::assertSame(['api/greetings/{name}', 'api/greetings'], array_keys($this->routes));
    }

    /**
     * The path template is Flow's placeholder syntax already, so an operation
     * path becomes a pattern that matches the requests it describes - which is
     * the only reason any of this is reachable.
     *
     * @test
     */
    public function aTemplatedPathMatchesTheRequestsItDescribes(): void
    {
        $route = $this->routes['api/greetings/{name}'];

        self::assertTrue($this->routeMatches($route, '/api/greetings/hello'));
        self::assertFalse($this->routeMatches($route, '/api/greetings/hello/extra'), 'a placeholder is one segment');
    }

    private function routeMatches(Route $route, string $path): bool
    {
        ob_start();
        $matches = $route->matches(new RouteContext(new ServerRequest('GET', $path), RouteParameters::createEmpty()));
        ob_end_clean();

        return $matches;
    }

    /**
     * Every generated route points at the one dispatch controller: which
     * method actually answers is the dispatch table's business, not routing's.
     *
     * @test
     */
    public function everyRouteIsAnsweredByTheDispatchController(): void
    {
        foreach ($this->routes as $route) {
            self::assertSame([
                '@package' => 'Medienreaktor.NeosApi',
                '@subpackage' => 'Framework',
                '@controller' => 'OpenApiDispatch',
                '@format' => 'json',
            ], $route->getDefaults());
        }
    }

    /**
     * A route that accepted every method would swallow requests the operation
     * does not answer, and Flow would dispatch them into the handler instead
     * of leaving them unrouted.
     *
     * @test
     */
    public function aRouteAcceptsOnlyTheOperationsOwnMethod(): void
    {
        self::assertSame(['GET'], $this->routes['api/greetings']->getHttpMethods());
    }

    /**
     * The name is what ./flow routing:list shows, which is the only place an
     * operator ever sees these routes - so it names the operation.
     *
     * @test
     */
    public function theRouteIsNamedAfterTheOperation(): void
    {
        self::assertSame('API (generated): showGreeting', $this->routes['api/greetings/{name}']->getName());
    }
}
