<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\Flow\Annotations as Flow;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Compilation\CompiledApi;

/**
 * Compiles the API once per request and hands out both halves: the document
 * to publish and the dispatch table to serve from.
 *
 * Compilation is the only place reflection happens, and it is bounded by the
 * registered Api classes - so it is memoized per request rather than cached
 * across them. If that ever costs measurably, CompiledApi is plain data and
 * a Flow VariableFrontend keyed on a code hash is the next step; measuring
 * first is deliberate.
 */
#[Flow\Scope('singleton')]
final class CompiledApiProvider
{
    private ?CompiledApi $compiled = null;

    public function __construct(
        private readonly ApiDefinitionFactory $apiDefinitionFactory,
    ) {
    }

    public function get(): CompiledApi
    {
        return $this->compiled ??= (new ApiCompiler())->compile($this->apiDefinitionFactory->create());
    }
}
