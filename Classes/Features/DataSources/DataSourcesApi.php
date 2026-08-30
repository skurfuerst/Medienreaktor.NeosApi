<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources;

use Medienreaktor\NeosApi\Features\DataSources\Dto\DataSourceResult;
use Medienreaktor\NeosApi\Features\DataSources\Response\DataSourceFailed;
use Medienreaktor\NeosApi\Features\DataSources\Response\DataSourceNotFound;
use Medienreaktor\NeosApi\Features\DataSources\Response\InvalidNodeAddress;
use Medienreaktor\NeosApi\Features\DataSources\Response\NodeNotFound;
use Medienreaktor\NeosApi\Service\NodeAddressCodec;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Neos\Service\Controller\DataSourceController;
use Neos\Neos\Service\DataSource\DataSourceInterface;
use Neos\OpenApi\Attributes\Operation;
use Neos\Utility\ObjectAccess;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Bearer-token access to the Neos data sources (DataSourceInterface
 * implementations) that back select-box style editors. This wraps the same
 * discovery and invocation the session-authenticated core endpoint
 * (/neos/service/data-source) performs, so every existing data source works
 * unchanged - only the transport differs:
 *
 *  - the node is passed as a base64url node address (the API-wide codec),
 *    not the raw NodeAddress JSON the old UI sends
 *  - the raw getData() return value is wrapped in a {"data": ...} envelope,
 *    keeping error responses ({"error": ...}) distinguishable and the
 *    top level an object regardless of what the data source returns
 *
 * The one operation in this API whose contract is not fully expressible as a
 * signature: every query parameter besides `node` is forwarded verbatim as the
 * data source's $arguments (the editorOptions.dataSourceAdditionalData
 * contract), so no signature can name them. It therefore takes the request
 * itself - an argument neos/openapi binds by type and keeps out of the
 * document. Nothing else in this package should need one.
 *
 * The scope requirement lives in the operation's own security declaration,
 * which is also what publishes it in the OpenAPI document; the account's roles
 * are checked by the Flow policy layer against the matcher for this class.
 */
class DataSourcesApi
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    #[Flow\Inject]
    protected UriBuilder $uriBuilder;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'contentRepository')]
    protected string $contentRepositoryId;

    #[Operation(
        path: '/api/data-sources/{dataSourceIdentifier}',
        method: 'GET',
        summary: 'Invoke a Neos data source',
        description: 'Calls a registered `DataSourceInterface` implementation (the ones backing select-box '
            . 'editors). `node` is an optional base64url node address giving the data source its node context. '
            . 'All *other* query parameters are forwarded verbatim to the data source as its arguments '
            . '(`editorOptions.dataSourceAdditionalData` contract), which is why the document cannot name them.',
        operationId: 'invokeDataSource',
        security: ['oauth2' => ['neos.read']],
    )]
    public function show(
        ServerRequestInterface $request,
        string $dataSourceIdentifier,
        ?string $node = null,
    ): DataSourceResult|DataSourceNotFound|InvalidNodeAddress|NodeNotFound|DataSourceFailed {
        // Reuse the core controller's compile-static registry so identifier
        // resolution (and its duplicate-identifier guarantee) stays identical.
        $dataSources = DataSourceController::getDataSources($this->objectManager);
        if (!isset($dataSources[$dataSourceIdentifier])) {
            return new DataSourceNotFound($dataSourceIdentifier);
        }

        $contextNode = null;
        if ($node !== null && $node !== '') {
            $address = $this->decodeNodeAddress($node);
            if ($address instanceof InvalidNodeAddress) {
                return $address;
            }
            $contextNode = $this->contentRepositoryRegistry
                ->get($address->contentRepositoryId)
                ->getContentSubgraph($address->workspaceName, $address->dimensionSpacePoint)
                ->findNodeById($address->aggregateId);
            if ($contextNode === null) {
                return new NodeNotFound();
            }
        }

        /** @var DataSourceInterface $dataSource */
        $dataSource = new $dataSources[$dataSourceIdentifier]();
        // AbstractDataSource exposes a controllerContext some data sources use
        // (e.g. to build URIs) - inject ours the way the core endpoint does.
        if (ObjectAccess::isPropertySettable($dataSource, 'controllerContext')) {
            ObjectAccess::setProperty($dataSource, 'controllerContext', $this->controllerContext($request));
        }

        try {
            $value = $dataSource->getData($contextNode, self::forwardedArguments($request));
        } catch (\Exception $exception) {
            // The exception text stays out of the response: data sources are
            // arbitrary third-party code and their messages may leak internals.
            $this->logger->error(
                sprintf('Data source "%s" threw: %s', $dataSourceIdentifier, $exception->getMessage()),
                ['exception' => $exception]
            );

            return new DataSourceFailed($dataSourceIdentifier);
        }

        return new DataSourceResult($value);
    }

    /**
     * Everything the operation did not declare, which is precisely what the
     * data source contract calls its $arguments. The two names this endpoint
     * owns are removed - `dataSourceIdentifier` because the old route merged
     * the path segment into the arguments and then dropped it, so a client
     * that also passes it as a query parameter sees what it always saw.
     *
     * @return array<string, mixed>
     */
    private static function forwardedArguments(ServerRequestInterface $request): array
    {
        $arguments = $request->getQueryParams();
        unset($arguments['dataSourceIdentifier'], $arguments['node']);

        return $arguments;
    }

    /**
     * Same two rejections {@see \Medienreaktor\NeosApi\Controller\Api\AbstractApiController::decodeNodeAddress()}
     * makes, returning the 400 rather than throwing it.
     */
    private function decodeNodeAddress(string $encoded): NodeAddress|InvalidNodeAddress
    {
        try {
            $address = NodeAddressCodec::decode($encoded);
        } catch (\Throwable) {
            return new InvalidNodeAddress('The given node address could not be parsed.');
        }
        if ($address->contentRepositoryId->value !== $this->contentRepositoryId) {
            return new InvalidNodeAddress(sprintf(
                'The node address belongs to content repository "%s", this API serves "%s".',
                $address->contentRepositoryId->value,
                $this->contentRepositoryId
            ));
        }

        return $address;
    }

    /**
     * A data source may reach for a controllerContext to build URIs. There is
     * no controller here - the library dispatches to this object directly - so
     * one is assembled from the request being served. The URIs it builds are
     * unaffected by the dispatch controller having stripped the application's
     * base path: UriBuilder takes its base URI from the configured one, or
     * from the request's scheme and host, never from the path.
     */
    private function controllerContext(ServerRequestInterface $request): ControllerContext
    {
        $actionRequest = ActionRequest::fromHttpRequest($request);
        $uriBuilder = clone $this->uriBuilder;
        $uriBuilder->setRequest($actionRequest);

        return new ControllerContext($actionRequest, new ActionResponse(), new Arguments(), $uriBuilder);
    }
}
