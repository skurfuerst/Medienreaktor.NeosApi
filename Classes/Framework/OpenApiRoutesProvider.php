<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\Flow\Mvc\Routing\Route;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\Flow\Mvc\Routing\Routes;
use Neos\Flow\Mvc\Routing\RoutesProviderInterface;

/**
 * One Flow route per compiled operation, so the generated API lives in
 * routing rather than beside it: ./flow routing:list shows every operation,
 * and requests reach OpenApiDispatchController through the ordinary chain -
 * which is where the ActionRequest, the security context and authentication
 * come from.
 *
 * The routes carry no arguments. Path variables are matched by the library
 * against the operation's own path template and coerced into its argument
 * types, so Flow only needs to recognise the shape of the URI; hence the
 * wildcard part for every placeholder.
 */
final readonly class OpenApiRoutesProvider implements RoutesProviderInterface
{
    public function __construct(
        private CompiledApi $compiledApi,
    ) {
    }

    public function getRoutes(): Routes
    {
        $routes = [];
        foreach ($this->compiledApi->dispatchTable->all() as $key => $entry) {
            [$method, $path] = explode(' ', $key, 2);
            $routes[] = Route::fromConfiguration([
                'name' => sprintf('API (generated): %s', $entry->operationId ?? $key),
                'uriPattern' => ltrim($path, '/'),
                'httpMethods' => [$method],
                'defaults' => [
                    '@package' => 'Medienreaktor.NeosApi',
                    '@subpackage' => 'Framework',
                    '@controller' => 'OpenApiDispatch',
                    '@format' => 'json',
                ],
            ]);
        }

        return Routes::create(...$routes);
    }
}
