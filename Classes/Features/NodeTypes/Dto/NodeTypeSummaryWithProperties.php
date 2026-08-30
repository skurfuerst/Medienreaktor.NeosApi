<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes\Dto;

use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * A node type entry as `includeProperties=1` answers it: the summary plus the
 * merged property and reference declarations.
 *
 * A separate class rather than two nullable members on {@see NodeTypeSummary},
 * because neos/schematic serializes every constructor parameter - a DTO cannot
 * omit a key - and these two have always been absent unless asked for. The two
 * classes are therefore what the response really is: a union, published as one
 * (see {@see NodeTypes}).
 *
 * TODO: collapse this back into NodeTypeSummary once neos/schematic can mark a
 * member as omitted-when-absent; then `includeProperties` is one nullable
 * parameter rather than a second class.
 *
 * Both maps are keyed by property/reference name, so they are carried as plain
 * arrays with a hand-written schema (schematic has no map nature). Their values
 * cannot be typed at all in the published document - neos/jsonschema's
 * `additionalProperties` is a bool - so what the entries look like is said in
 * prose here and enforced by the code that fills them.
 */
final readonly class NodeTypeSummaryWithProperties implements ProvidesSchema
{
    /**
     * @param array<string> $superTypes
     * @param array<string, array{type: string|null, label: string|null}> $properties
     * @param array<string, array{label: string|null, maxItems: int|null}> $references
     */
    public function __construct(
        public string $name,
        public bool $abstract,
        public array $superTypes,
        public ?string $label,
        public ?string $icon,
        public ?string $group,
        public mixed $position,
        public array $properties,
        public array $references,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                ...NodeTypeSummary::properties(),
                ...[
                    'properties' => ObjectSchema::create(
                        description: 'Property name -> `{type, label}`. Underscore-prefixed internals are excluded.',
                        additionalProperties: true,
                    ),
                    'references' => ObjectSchema::create(
                        description: 'Reference name -> `{label, maxItems}`, where `maxItems: 1` marks a singular reference.',
                        additionalProperties: true,
                    ),
                ],
            ),
            additionalProperties: false,
            required: ['name', 'abstract', 'superTypes', 'label', 'icon', 'group', 'position', 'properties', 'references'],
        );
    }
}
