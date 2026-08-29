<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework;

use Medienreaktor\NeosApi\Framework\SpecMerger;
use Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures\GreetingApi;
use Neos\Flow\Tests\UnitTestCase;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Compilation\CompiledApi;
use Neos\OpenApi\Spec\InfoObject;
use Neos\OpenApi\Spec\SecuritySchemeObject;
use Neos\OpenApi\Spec\SecuritySchemeOrReferenceObjectMap;

/**
 * GET /api/openapi.json serves one document assembled from two halves, and a
 * client cannot tell which half an endpoint came from - that is the whole
 * point of the merge, so it is what these check.
 */
class SpecMergerTest extends UnitTestCase
{
    private SpecMerger $merger;

    private CompiledApi $compiled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merger = new SpecMerger();
        $this->compiled = (new ApiCompiler())->compile(
            ApiDefinition::create(
                info: new InfoObject('Neos API', '1.9'),
                securitySchemes: SecuritySchemeOrReferenceObjectMap::create()->with('oauth2', SecuritySchemeObject::bearer()),
            )->withOperationsFrom(GreetingApi::class, tag: 'Greetings')
        );
    }

    /**
     * @test
     */
    public function generatedOperationsJoinTheHandMaintainedOnes(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);

        self::assertArrayHasKey('/api/legacy', $merged['paths'], 'the hand-maintained half must survive');
        self::assertArrayHasKey('/api/greetings/{name}', $merged['paths']);
        self::assertSame('showGreeting', $merged['paths']['/api/greetings/{name}']['get']['operationId']);
    }

    /**
     * The YAML's tag list is what orders the reference page's sidebar, so a
     * generated tag is appended rather than merged in, and only when it names
     * something the document does not already have.
     *
     * @test
     */
    public function tagsAreAppendedOnlyWhenTheyAreNew(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);
        self::assertSame(['Meta', 'Greetings'], array_column($merged['tags'], 'name'));

        $spec = $this->spec();
        $spec['tags'][] = ['name' => 'Greetings', 'description' => 'The hand-written description wins'];
        $merged = $this->merger->merge($spec, $this->compiled);

        self::assertSame(['Meta', 'Greetings'], array_column($merged['tags'], 'name'));
        self::assertSame('The hand-written description wins', $merged['tags'][1]['description']);
    }

    /**
     * The library documents its own failures as problem+json, but
     * LegacyErrorTranslator rewrites those on the wire - so the document has
     * to say what actually goes out, or it advertises a shape no client will
     * ever receive.
     *
     * @test
     */
    public function generatedFailuresAreDocumentedInTheLegacyShape(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);
        $responses = $merged['paths']['/api/greetings/{name}']['get']['responses'];

        self::assertSame(['$ref' => '#/components/responses/Unauthorized'], $responses[401]);
        self::assertSame(
            ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
            $responses[400]['content'],
            'a 400 keeps a body, but the envelope one'
        );
        self::assertArrayNotHasKey(
            'ProblemDocument',
            $merged['components']['schemas'],
            'nothing references it any more, and an unreachable component is noise'
        );
    }

    /**
     * Neither 403 is visible to the compiler: a missing scope is refused by
     * the auth context provider, and a missing role by the Flow policy layer.
     * The hand-maintained document has always declared it.
     *
     * @test
     */
    public function everySecuredOperationDocumentsAForbidden(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);

        self::assertSame(
            ['$ref' => '#/components/responses/Forbidden'],
            $merged['paths']['/api/greetings/{name}']['get']['responses'][403]
        );
        self::assertArrayNotHasKey(
            403,
            $merged['paths']['/api/greetings']['get']['responses'],
            'an operation nobody needs credentials for cannot be forbidden'
        );
    }

    /**
     * @test
     */
    public function schemasTheGeneratedOperationsUseAreCarriedOver(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);

        self::assertSame(['type' => 'string', 'minLength' => 1], $merged['components']['schemas']['Greeting']);
        self::assertArrayHasKey('Error', $merged['components']['schemas'], 'the hand-maintained schemas stay');
    }

    /**
     * The hand-maintained document is the spine: it owns info, servers, the
     * default security requirement and the security schemes, because
     * DocsController stamps the request-dependent parts into those at serve
     * time.
     *
     * @test
     */
    public function theHandMaintainedDocumentKeepsItsOwnFrame(): void
    {
        $merged = $this->merger->merge($this->spec(), $this->compiled);

        self::assertSame('The missing HTTP API for Neos 9.', $merged['info']['summary']);
        self::assertSame([['url' => 'http://localhost:8080']], $merged['servers']);
        self::assertSame(['oauth2' => ['type' => 'oauth2']], $merged['components']['securitySchemes']);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Neos API', 'version' => '1.9', 'summary' => 'The missing HTTP API for Neos 9.'],
            'servers' => [['url' => 'http://localhost:8080']],
            'security' => [['oauth2' => []]],
            'tags' => [['name' => 'Meta']],
            'paths' => [
                '/api/legacy' => ['get' => ['operationId' => 'legacy', 'responses' => ['200' => ['description' => 'OK']]]],
            ],
            'components' => [
                'securitySchemes' => ['oauth2' => ['type' => 'oauth2']],
                'schemas' => ['Error' => ['type' => 'object']],
                'responses' => ['Unauthorized' => ['description' => 'no token'], 'Forbidden' => ['description' => 'denied']],
            ],
        ];
    }
}
