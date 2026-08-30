<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\NullSchema;
use Neos\JsonSchema\NumberSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\OneOfSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * One node type as tree and creation UIs need it: identity, where it sits in
 * the type hierarchy, and the three ui.* values that decide how it is offered.
 *
 * The schema is hand-written because `position` is whatever the node type
 * configured - Neos accepts a string ("start 100", "after other") as readily
 * as a number - and neos/schematic derives schemas from constructor types,
 * which cannot express that union.
 *
 * @see NodeTypeSummaryWithProperties for the includeProperties=1 variant
 */
final readonly class NodeTypeSummary implements ProvidesSchema
{
    public function __construct(
        public string $name,
        public bool $abstract,
        /** @var array<string> */
        public array $superTypes,
        public ?string $label,
        public ?string $icon,
        public ?string $group,
        public mixed $position,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(...self::properties()),
            additionalProperties: false,
            required: ['name', 'abstract', 'superTypes', 'label', 'icon', 'group', 'position'],
        );
    }

    /**
     * The seven members every entry carries, shared with the variant that adds
     * the property and reference declarations to them.
     *
     * @return array<string, Schema>
     */
    public static function properties(): array
    {
        return [
            'name' => StringSchema::create(),
            'abstract' => BooleanSchema::create(),
            'superTypes' => ArraySchema::create(
                description: 'The directly declared super types, most specific first.',
                items: StringSchema::create(),
            ),
            'label' => OneOfSchema::create(
                StringSchema::create(description: 'Raw `ui.label` - may be an untranslated XLIFF id.'),
                NullSchema::create(),
            ),
            'icon' => OneOfSchema::create(
                StringSchema::create(description: 'Normalized Font Awesome class.'),
                NullSchema::create(),
            ),
            'group' => OneOfSchema::create(
                StringSchema::create(description: '`null` means the type is not offered in creation dialogs.'),
                NullSchema::create(),
            ),
            'position' => OneOfSchema::create(
                StringSchema::create(),
                NumberSchema::create(),
                NullSchema::create(),
            ),
        ];
    }
}
