<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * A response body carrying both kinds of empty array: a free-form map, and a
 * list. Only the map may be published as `{}`, and only the schema says so.
 */
final readonly class Maps implements ProvidesSchema
{
    /**
     * @param array<string, mixed> $entries
     * @param array<string> $names
     */
    public function __construct(
        public array $entries,
        public array $names,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                entries: ObjectSchema::create(additionalProperties: true),
                names: ArraySchema::create(items: StringSchema::create()),
            ),
            additionalProperties: false,
            required: ['entries', 'names'],
        );
    }
}
