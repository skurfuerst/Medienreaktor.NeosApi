<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * What GET /api/users/roles answers: the assignable role catalog, sorted by
 * label.
 */
final readonly class AssignableRoles implements ProvidesSchema
{
    /**
     * @param array<AssignableRole> $roles
     */
    public function __construct(
        public array $roles,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                roles: ArraySchema::create(description: 'Sorted by label.', items: AssignableRole::schema()),
            ),
            additionalProperties: false,
            required: ['roles'],
        );
    }
}
