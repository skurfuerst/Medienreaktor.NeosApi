<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * What GET /api/node-types answers: every node type, and the creation-dialog
 * groups they sort into.
 *
 * The entries are a union - {@see NodeTypeSummary} or, with
 * `includeProperties=1`, {@see NodeTypeSummaryWithProperties} - which is why
 * they are held in a plain array rather than a variadic collection: a
 * collection DTO publishes the schema of its variadic parameter's *class*, and
 * a union has none. As a builtin array the schema written here is what gets
 * published, and its `oneOf` says exactly which of the two arrives.
 *
 * `groups` is the `Neos.Neos.nodeTypes.groups` setting passed through
 * verbatim - a free-form map, so it travels as an array as well (see the
 * package's CLAUDE.md on why maps work this way).
 */
final readonly class NodeTypes implements ProvidesSchema
{
    /**
     * @param array<NodeTypeSummary|NodeTypeSummaryWithProperties> $nodeTypes
     * @param array<string, array<string, mixed>> $groups
     */
    public function __construct(
        public array $nodeTypes,
        public array $groups,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                nodeTypes: ArraySchema::create(
                    description: 'Including abstract node types, in node type manager order.',
                    items: OneOfSchema::create(
                        NodeTypeSummary::schema(),
                        NodeTypeSummaryWithProperties::schema(),
                    ),
                ),
                groups: ObjectSchema::create(
                    description: 'Group name -> `{label, position, collapsed}`; `Neos.Neos.nodeTypes.groups` verbatim.',
                    additionalProperties: true,
                ),
            ),
            additionalProperties: false,
            required: ['nodeTypes', 'groups'],
        );
    }
}
