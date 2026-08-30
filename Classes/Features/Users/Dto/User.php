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
 * A backend user as the administration sees them.
 *
 * The schema is hand-written because two members cannot be derived: `roles`
 * is a list of plain strings, and `accounts` a list of {@see Account} - both
 * builtin arrays rather than variadic collections, since neither deserves a
 * collection class of its own in the document.
 */
final readonly class User implements ProvidesSchema
{
    /**
     * @param array<string> $roles
     * @param array<Account> $accounts
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $firstName,
        public ?string $lastName,
        public string $fullName,
        public ?string $email,
        public bool $active,
        public array $roles,
        public array $accounts,
        public bool $isCurrentUser,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                id: StringSchema::create(pattern: '^[a-z0-9\-]{1,40}$'),
                label: StringSchema::create(description: 'Full name.'),
                firstName: Nullable::wrap(StringSchema::create()),
                lastName: Nullable::wrap(StringSchema::create()),
                fullName: StringSchema::create(),
                email: Nullable::wrap(StringSchema::create()),
                active: BooleanSchema::create(),
                roles: ArraySchema::create(
                    description: 'Explicitly assigned role identifiers across all accounts (no implicit roles).',
                    items: StringSchema::create(),
                ),
                accounts: ArraySchema::create(items: Account::schema()),
                isCurrentUser: BooleanSchema::create(
                    description: 'Lets a client mark "you" and disable the self-lockout operations up front.',
                ),
            ),
            additionalProperties: false,
            required: ['id', 'label', 'firstName', 'lastName', 'fullName', 'email', 'active', 'roles', 'accounts', 'isCurrentUser'],
        );
    }
}
