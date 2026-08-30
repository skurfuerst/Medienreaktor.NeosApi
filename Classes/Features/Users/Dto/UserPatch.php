<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\BooleanSchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * The body of PATCH /api/users/{userId}: every member optional, absent ones
 * left as they are.
 *
 * Value checks live in the operation, not here - see {@see NewUser} on why,
 * and for the TODO to move them in here once the frontend can read the
 * library's issue-level 400.
 *
 * "Absent" and "null" are the same thing here, exactly as they were under
 * Flow's argument mapping - a member the client leaves out arrives as `null`
 * and is skipped. The one member that distinguishes further is `email`, where
 * the empty string means "remove the address"; that is why it is a string and
 * not a nullable-with-meaning.
 */
final readonly class UserPatch implements ProvidesSchema
{
    /**
     * @param array<string>|null $roles
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?array $roles = null,
        public ?bool $active = null,
        public ?string $password = null,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                firstName: Nullable::wrap(StringSchema::create()),
                lastName: Nullable::wrap(StringSchema::create()),
                email: Nullable::wrap(StringSchema::create(description: 'The empty string removes the address.')),
                roles: Nullable::wrap(ArraySchema::create(
                    description: 'Replaces the assigned roles on every one of the user\'s accounts.',
                    items: StringSchema::create(),
                )),
                active: Nullable::wrap(BooleanSchema::create()),
                password: Nullable::wrap(StringSchema::create(description: 'An administrative reset - the old password is not required.')),
            ),
        );
    }
}
