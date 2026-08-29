<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\RoutesProviderFactoryInterface;
use Neos\Flow\Mvc\Routing\RoutesProviderInterface;

/**
 * Declared as a "providerFactory" entry in Routes.yaml, which is how Flow
 * lets routes come from somewhere other than configuration.
 */
#[Flow\Scope('singleton')]
final class OpenApiRoutesProviderFactory implements RoutesProviderFactoryInterface
{
    public function __construct(
        private readonly CompiledApiProvider $compiledApiProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createRoutesProvider(array $options): RoutesProviderInterface
    {
        return new OpenApiRoutesProvider($this->compiledApiProvider->get());
    }
}
