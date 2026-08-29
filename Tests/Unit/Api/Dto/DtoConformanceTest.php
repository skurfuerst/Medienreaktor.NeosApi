<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Api\Dto;

use Medienreaktor\NeosApi\Api\Dto\ContentDimension;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensions;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensionValue;
use Medienreaktor\NeosApi\Api\Dto\ContentDimensionValues;
use Medienreaktor\NeosApi\Api\Dto\Dimensions;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Schematic\Conformance;

/**
 * A DTO's schema is what the OpenAPI document publishes AND what incoming
 * data is validated against, so a schema that disagrees with its own class is
 * a contract this API cannot honour. A derived schema cannot disagree; a
 * hand-written one can, silently, until it bites at runtime.
 *
 * Every response DTO belongs in this list - the cheap ones prove they stayed
 * derivable, and the hand-written ones are the reason the test exists.
 */
class DtoConformanceTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function dtoProvider(): iterable
    {
        yield 'Dimensions (hand-written schema)' => [Dimensions::class];
        yield 'ContentDimensions' => [ContentDimensions::class];
        yield 'ContentDimension' => [ContentDimension::class];
        yield 'ContentDimensionValues' => [ContentDimensionValues::class];
        yield 'ContentDimensionValue' => [ContentDimensionValue::class];
    }

    /**
     * @test
     * @dataProvider dtoProvider
     * @param class-string $className
     */
    public function schemaAgreesWithTheClass(string $className): void
    {
        self::assertSame([], Conformance::check($className));
    }

    /**
     * The map workaround: dimension coordinates travel as a plain array
     * because neos/schematic cannot derive a schema for one. That only works
     * as long as the serializer keeps the string keys - if it ever stopped,
     * every client parsing a dimension space point would break, so it is
     * pinned here rather than assumed.
     *
     * @test
     */
    public function dimensionSpacePointsKeepTheirKeys(): void
    {
        $dimensions = new Dimensions(
            dimensions: new ContentDimensions(),
            allowedDimensionSpacePoints: [['language' => 'de'], ['language' => 'en_US']],
        );

        self::assertSame(
            ['dimensions' => [], 'allowedDimensionSpacePoints' => [['language' => 'de'], ['language' => 'en_US']]],
            \Neos\Schematic\Schematic::serialize($dimensions)
        );
    }
}
