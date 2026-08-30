<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures\MapApi;
use Neos\Flow\Tests\UnitTestCase;
use Neos\OpenApi\ApiDefinition;
use Neos\OpenApi\Compilation\ApiCompiler;
use Neos\OpenApi\Http\RequestHandler;
use Neos\OpenApi\Spec\InfoObject;
use Psr\Container\ContainerInterface;

/**
 * `{}` and `[]` are different JSON values, and PHP has one array type for
 * both - so an empty free-form map would go out as `[]` while the document
 * describes it as an object.
 *
 * The library answers that from the schema it published for the response
 * (`Schematic::serialize()` takes one), which this package used to have to
 * repair afterwards. This pins the wire format the API has always emitted,
 * end to end through the dispatcher rather than at a seam.
 */
class EmptyMapResponseTest extends UnitTestCase
{
    /**
     * @test
     */
    public function anEmptyMapGoesOutAsAnObjectAndAnEmptyListAsAnArray(): void
    {
        self::assertSame('{"entries":{},"names":[]}', $this->body());
    }

    private function body(): string
    {
        $httpFactory = new HttpFactory();
        $compiled = (new ApiCompiler())->compile(
            ApiDefinition::create(info: new InfoObject('Neos API', '1.9'))->withOperationsFrom(MapApi::class)
        );
        // The Api classes come out of Flow's container in production; here the one fixture class is the whole of
        // it, and asking for anything else is a mistake in the test rather than a case to serve.
        $apiClasses = new class () implements ContainerInterface {
            public function get(string $id): object
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException(sprintf('No Api class "%s" in this test container', $id), 1783600601);
                }
                return new MapApi();
            }

            public function has(string $id): bool
            {
                return $id === MapApi::class;
            }
        };
        $handler = new RequestHandler($compiled, $apiClasses, $httpFactory, $httpFactory);

        return (string)$handler->handle(new ServerRequest('GET', 'http://localhost/api/maps'))->getBody();
    }
}
