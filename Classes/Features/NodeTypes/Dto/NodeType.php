<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * What GET /api/node-types/{nodeTypeName} answers: one node type with its full
 * merged configuration.
 *
 * The configuration is the node type's own tree - ui, properties, references,
 * constraints, options and whatever a package added - so it is free-form by
 * definition and travels as an array with a hand-written schema, the same way
 * dimension coordinates do.
 */
final readonly class NodeType implements ProvidesSchema
{
    /**
     * @param array<string> $superTypes
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public string $name,
        public bool $abstract,
        public array $superTypes,
        public array $configuration,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                name: StringSchema::create(),
                abstract: BooleanSchema::create(),
                superTypes: ArraySchema::create(
                    description: 'The directly declared super types, most specific first.',
                    items: StringSchema::create(),
                ),
                configuration: ObjectSchema::create(
                    description: 'The full merged node type configuration (ui, properties, references, constraints, options, ...).',
                    additionalProperties: true,
                ),
            ),
            additionalProperties: false,
            required: ['name', 'abstract', 'superTypes', 'configuration'],
        );
    }
}
