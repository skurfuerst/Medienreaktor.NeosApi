<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework\Controller;

use GuzzleHttp\Psr7\HttpFactory;
use Medienreaktor\NeosApi\Framework\CompiledApiProvider;
use Medienreaktor\NeosApi\Framework\FlowAuthContextProvider;
use Medienreaktor\NeosApi\Framework\InsufficientScopeException;
use Medienreaktor\NeosApi\Framework\LegacyErrorTranslator;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\BaseUriProvider;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Security\Exception\AccessDeniedException;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\OpenApi\Http\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serves every migrated operation: the seam between Flow's MVC dispatch and
 * the library's PSR-15 request handler.
 *
 * It is a controller rather than a middleware on purpose. Routing has run by
 * the time this is reached, which means Flow has already built the
 * ActionRequest, put it on the security context and authenticated it exactly
 * as it does for any other request - the alternative, a middleware before
 * routing, would have to reproduce all of that. OpenApiRoutesProvider is what
 * puts the operations into routing in the first place, so they also show up
 * in ./flow routing:list.
 *
 * Not an ActionController: this has no actions, no view and no argument
 * mapping - the library does all three from the operation's own types. That
 * also keeps it out of Neos.Neos:AllControllerActions (which matches
 * *Action() methods within AbstractController), which is correct: the
 * privilege belongs on the Api class method, not here.
 */
final class OpenApiDispatchController implements ControllerInterface
{
    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    #[Flow\Inject]
    protected CompiledApiProvider $compiledApiProvider;

    #[Flow\Inject]
    protected FlowAuthContextProvider $authContextProvider;

    #[Flow\Inject]
    protected BaseUriProvider $baseUriProvider;

    public function processRequest(ActionRequest $request): ResponseInterface
    {
        $httpFactory = new HttpFactory();
        $errors = new LegacyErrorTranslator($httpFactory);

        try {
            $handler = new RequestHandler(
                $this->compiledApiProvider->get(),
                // Api classes are read out of Flow's container, so they are
                // Flow proxies - which is what makes the policy AOP advice fire
                // on their methods, and what gives them their dependencies.
                $this->objectManager,
                $httpFactory,
                $httpFactory,
                $this->authContextProvider,
            );
            $response = $errors->translate($handler->handle($this->applicationRelative($request->getHttpRequest())));
        } catch (InsufficientScopeException $exception) {
            $response = $errors->json(
                $httpFactory->createResponse(403),
                ['error' => 'insufficient_scope', 'error_description' => $exception->getMessage()]
            );
        } catch (AccessDeniedException $exception) {
            // The policy layer denied an authenticated account. Flow would
            // render its own error page here; an API answers in its own
            // envelope, which is what openapi.yaml documents for a 403.
            $response = $errors->json(
                $httpFactory->createResponse(403),
                ['error' => 'forbidden', 'error_description' => 'Your account is not permitted to use this endpoint.']
            );
        } catch (AuthenticationRequiredException $exception) {
            $response = $errors->json(
                $httpFactory->createResponse(401),
                ['error' => 'unauthorized', 'error_description' => 'No authenticated bearer token present.']
            );
        }

        // Every response is per-account (subgraphs apply the account's
        // visibility constraints), so it must never end up in a shared cache -
        // and an editing API wants fresh data, so no client caching either.
        return $response
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Vary', 'Authorization');
    }

    /**
     * The request as the API document sees it: paths in the document are
     * application-relative ("/api/dimensions"), while the request's URI
     * carries whatever prefix the application is mounted under. The library
     * matches on the raw URI path, so the prefix comes off here.
     */
    private function applicationRelative(ServerRequestInterface $httpRequest): ServerRequestInterface
    {
        $basePath = rtrim($this->baseUriProvider->getConfiguredBaseUriOrFallbackToCurrentRequest($httpRequest)->getPath(), '/');
        if ($basePath === '') {
            return $httpRequest;
        }
        $path = $httpRequest->getUri()->getPath();
        if (!str_starts_with($path, $basePath . '/')) {
            return $httpRequest;
        }

        return $httpRequest->withUri($httpRequest->getUri()->withPath(substr($path, strlen($basePath))));
    }
}
