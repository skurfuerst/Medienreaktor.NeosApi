<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Dimensions\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * What GET /api/dimensions answers: the dimension configuration, and every
 * combination of values a node address may legally carry.
 *
 * The schema is hand-written for one reason: a dimension space point is a
 * free-form map (dimension id -> value), and neos/schematic derives schemas
 * from constructor parameters, which cannot express one. The map therefore
 * travels as a plain array - the serializer preserves its string keys, so the
 * JSON is unchanged - and this says what it contains. Note that jsonschema
 * cannot yet type the values either (additionalProperties is a bool), so the
 * published schema is looser than the hand-maintained YAML was; the wire
 * format is not.
 */
final readonly class Dimensions implements ProvidesSchema
{
    /**
     * @param array<array<string, string>> $allowedDimensionSpacePoints
     */
    public function __construct(
        public ContentDimensions $dimensions,
        public array $allowedDimensionSpacePoints,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                dimensions: ContentDimensions::schema(),
                allowedDimensionSpacePoints: ArraySchema::create(
                    description: 'For a dimension-less repository this is a single empty point.',
                    items: ObjectSchema::create(
                        description: 'Dimension coordinates, e.g. `{"language": "de"}`.',
                        additionalProperties: true,
                    ),
                ),
            ),
            additionalProperties: false,
            required: ['dimensions', 'allowedDimensionSpacePoints'],
        );
    }
}
