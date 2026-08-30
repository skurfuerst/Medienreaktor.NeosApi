<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources\Dto;

use Neos\JsonSchema\AnySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * The `{"data": ...}` envelope around whatever a data source returned.
 *
 * The envelope exists so the top level is an object no matter what the data
 * source produced - which keeps an error body ({"error": ...}) distinguishable
 * from a successful one, and leaves room to add envelope members later.
 *
 * `data` itself is genuinely unconstrained, and says so: a data source is
 * third-party code whose return value is only required to be JSON-serializable
 * (conventionally a list of `{value, label}` options, but the Neos core's own
 * form-definition source returns a map). `AnySchema` is the empty schema `{}`
 * - it accepts all of that, still carries the description, and leaves the
 * value untouched on the way out, so what the data source built is what
 * clients receive.
 */
final readonly class DataSourceResult implements ProvidesSchema
{
    public function __construct(
        public mixed $data,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                data: AnySchema::create(
                    description: 'The raw getData() return value - the shape is data-source specific; '
                        . 'conventionally a list of `{value, label}` options.',
                ),
            ),
            additionalProperties: false,
            required: ['data'],
        );
    }
}
