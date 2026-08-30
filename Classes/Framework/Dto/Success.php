<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework\Dto;

use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * `{"success": true}` - what this API answers to a deletion that had nothing
 * else to say.
 *
 * The schema is hand-written to reproduce the `Success` component the
 * hand-maintained document has always published, character for character in
 * meaning: the generated components are merged over the document's own by
 * name, and six not-yet-migrated operations still point at this one.
 *
 * The schema is also what keeps this an object on the wire: a class whose one
 * constructor parameter is a builtin scalar is written exactly like a
 * scalar-backed value object, and `type: object` here is the only thing that
 * says `Success` is not one.
 */
final readonly class Success implements ProvidesSchema
{
    public function __construct(
        public bool $success = true,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(success: BooleanSchema::create(const: true)),
            required: ['success'],
        );
    }
}
