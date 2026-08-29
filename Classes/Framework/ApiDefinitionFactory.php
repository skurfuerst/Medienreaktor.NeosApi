<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\Flow\Annotations as Flow;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\OAuthFlowObject;
use Neos\OpenApi\Spec\OAuthFlowsObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;
use Neos\Utility\PositionalArraySorter;

/**
 * The one description of this API: everything that is not an operation.
 *
 * Api classes are registered through settings rather than in code, so adding
 * one is a configuration entry (and a Policy matcher) rather than a change
 * here - which is what makes the controller-by-controller migration a series
 * of small, independent steps.
 *
 * The URLs in the oauth2 scheme are placeholders: the document this factory
 * feeds is merged into the served one by DocsController, which stamps the
 * request-dependent parts (issuer, scope catalog) at serve time as it always
 * has. They matter here only because the library derives the 401 challenge
 * from the declared scheme type.
 */
#[Flow\Scope('singleton')]
final class ApiDefinitionFactory
{
    /**
     * @var array<string, array{className: string, tag?: string, tagDescription?: string, position?: string}>
     */
    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'openApi.apiClasses')]
    protected array $apiClasses = [];

    /**
     * @var array<string, string>
     */
    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'oauth.scopes')]
    protected array $scopes = [];

    #[Flow\InjectConfiguration(package: 'Medienreaktor.NeosApi', path: 'openApi.info')]
    protected array $info = [];

    public function create(): ApiDefinition
    {
        $definition = ApiDefinition::create(
            info: new InfoObject(
                title: (string)($this->info['title'] ?? 'Neos API'),
                version: (string)($this->info['version'] ?? '1.0'),
            ),
            securitySchemes: $this->securitySchemes(),
        );

        foreach ((new PositionalArraySorter($this->apiClasses))->toArray() as $key => $registration) {
            if ($registration === null || !isset($registration['className'])) {
                continue;
            }
            /** @var class-string $className */
            $className = $registration['className'];
            $definition = $definition->withOperationsFrom(
                $className,
                tag: $registration['tag'] ?? $key,
                tagDescription: $registration['tagDescription'] ?? null,
            );
        }

        return $definition;
    }

    private function securitySchemes(): SecuritySchemeOrReferenceObjectMap
    {
        return SecuritySchemeOrReferenceObjectMap::create()
            ->with('oauth2', SecuritySchemeObject::oauth2(new OAuthFlowsObject(
                authorizationCode: new OAuthFlowObject(
                    scopes: $this->scopes,
                    authorizationUrl: '/oauth/authorize',
                    tokenUrl: '/oauth/token',
                    refreshUrl: '/oauth/token',
                ),
            )))
            ->with('bearer', SecuritySchemeObject::bearer());
    }
}
