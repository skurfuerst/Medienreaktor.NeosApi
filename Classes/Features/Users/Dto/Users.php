<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * What GET /api/users answers: every user, unpaginated - a Neos installation
 * has editors, not customers.
 */
final readonly class Users implements ProvidesSchema
{
    /**
     * @param array<User> $users
     */
    public function __construct(
        public array $users,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                users: ArraySchema::create(description: 'All users (no pagination).', items: User::schema()),
            ),
            additionalProperties: false,
            required: ['users'],
        );
    }
}
