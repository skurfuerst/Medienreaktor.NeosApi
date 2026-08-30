<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Features;

use Medienreaktor\NeosApi\Features\DataSources\Dto\DataSourceResult;
use Medienreaktor\NeosApi\Features\Dimensions\Dto\ContentDimension;
use Medienreaktor\NeosApi\Features\Dimensions\Dto\ContentDimensions;
use Medienreaktor\NeosApi\Features\Dimensions\Dto\ContentDimensionValue;
use Medienreaktor\NeosApi\Features\Dimensions\Dto\ContentDimensionValues;
use Medienreaktor\NeosApi\Features\Dimensions\Dto\Dimensions;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeType;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypes;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypeSummary;
use Medienreaktor\NeosApi\Features\NodeTypes\Dto\NodeTypeSummaryWithProperties;
use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
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
        yield 'NodeTypes (hand-written schema)' => [NodeTypes::class];
        yield 'NodeTypeSummary (hand-written schema)' => [NodeTypeSummary::class];
        yield 'NodeTypeSummaryWithProperties (hand-written schema)' => [NodeTypeSummaryWithProperties::class];
        yield 'NodeType (hand-written schema)' => [NodeType::class];
        yield 'DataSourceResult (hand-written schema)' => [DataSourceResult::class];
        yield 'LegacyError' => [LegacyError::class];
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
            \Neos\Schematic\Schematic::serialize(\Neos\Schematic\Schematic::schemaFor($dimensions::class), $dimensions)
        );
    }

    /**
     * The two node type entry classes are a union in the document, and which
     * one is used decides which keys a client sees - so both the key order and
     * the fact that only the detailed one carries the two maps are pinned. A
     * DTO cannot omit a member, which is exactly why there are two classes.
     *
     * @test
     */
    public function nodeTypeEntriesSerializeToTheDocumentedKeys(): void
    {
        $summary = new NodeTypeSummary(
            name: 'Neos.Neos:Document',
            abstract: false,
            superTypes: ['Neos.Neos:Node'],
            label: 'Document',
            icon: 'fas fa-file',
            group: 'general',
            position: 'start 100',
        );
        $detailed = new NodeTypeSummaryWithProperties(
            name: 'Neos.Neos:Document',
            abstract: false,
            superTypes: ['Neos.Neos:Node'],
            label: 'Document',
            icon: 'fas fa-file',
            group: 'general',
            position: 'start 100',
            properties: ['title' => ['type' => 'string', 'label' => 'Title']],
            references: [],
        );

        self::assertSame(
            ['name', 'abstract', 'superTypes', 'label', 'icon', 'group', 'position'],
            array_keys((array)\Neos\Schematic\Schematic::serialize(\Neos\Schematic\Schematic::schemaFor($summary::class), $summary))
        );
        self::assertSame(
            ['name', 'abstract', 'superTypes', 'label', 'icon', 'group', 'position', 'properties', 'references'],
            array_keys((array)\Neos\Schematic\Schematic::serialize(\Neos\Schematic\Schematic::schemaFor($detailed::class), $detailed))
        );
        self::assertSame([], Conformance::checkSerialization($summary));
        self::assertSame([], Conformance::checkSerialization($detailed));
    }

    /**
     * A data source's return value is third-party code's, and the envelope
     * publishes it as the empty schema: unconstrained, and therefore also
     * unprojected - what the data source built is what goes on the wire. The
     * shapes this endpoint has always emitted are pinned here, including the
     * one no locally available data source produces (an empty result), since
     * an empty PHP array is exactly where a schema would otherwise interfere.
     *
     * @test
     * @dataProvider dataSourceValueProvider
     */
    public function aDataSourceResultIsForwardedVerbatim(mixed $value, string $expectedJson): void
    {
        self::assertSame(
            $expectedJson,
            json_encode(
                \Neos\Schematic\Schematic::serialize(DataSourceResult::schema(), new DataSourceResult($value)),
                JSON_UNESCAPED_SLASHES
            )
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function dataSourceValueProvider(): iterable
    {
        // The conventional shape: a list of options.
        yield 'list' => [[['value' => 'a', 'label' => 'A']], '{"data":[{"value":"a","label":"A"}]}'];
        // An empty one must stay a list: `AnySchema` describes no structure,
        // so nothing reshapes it into the `{}` an ObjectSchema would imply.
        yield 'empty list' => [[], '{"data":[]}'];
        // What Neos' own form-definition data source returns: a map.
        yield 'map' => [['' => ['label' => '']], '{"data":{"":{"label":""}}}'];
        yield 'null' => [null, '{"data":null}'];
        yield 'scalar' => ['plain', '{"data":"plain"}'];
    }

    /**
     * The 76 empty `references` maps of
     * `GET /api/node-types?includeProperties=1`: PHP has one array type, so
     * only the schema can say that an empty one is `{}` rather than `[]` -
     * which is why `Schematic::serialize()` takes the schema. This also pins
     * that the `oneOf` picks the detailed branch, since that is where the two
     * maps live.
     *
     * @test
     */
    public function emptyMapsGoOutAsObjects(): void
    {
        $nodeTypes = new NodeTypes(
            nodeTypes: [
                new NodeTypeSummaryWithProperties(
                    name: 'Neos.Neos:Document',
                    abstract: false,
                    superTypes: ['Neos.Neos:Node'],
                    label: 'Document',
                    icon: 'fas fa-file',
                    group: 'general',
                    position: 'start 100',
                    properties: [],
                    references: [],
                ),
            ],
            groups: [],
        );

        $serialized = \Neos\Schematic\Schematic::serialize(NodeTypes::schema(), $nodeTypes);

        self::assertSame(
            '{"nodeTypes":[{"name":"Neos.Neos:Document","abstract":false,"superTypes":["Neos.Neos:Node"],'
            . '"label":"Document","icon":"fas fa-file","group":"general","position":"start 100",'
            . '"properties":{},"references":{}}],"groups":{}}',
            json_encode($serialized, JSON_UNESCAPED_SLASHES)
        );
    }
}
