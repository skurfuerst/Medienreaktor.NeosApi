<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ArraySchema;
use Neos\JsonSchema\Nullable;
use Neos\JsonSchema\ObjectSchema;
use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\JsonSchema\Support\ObjectProperties;

/**
 * The body of POST /api/users.
 *
 * The four required members are what a user cannot exist without; `roles` and
 * `email` are optional and keep the three-way distinction the endpoint has
 * always made for roles: absent means "Neos' own defaults", `[]` means
 * "explicitly none".
 *
 * Only structural validation lives in this schema: the members, their types,
 * and which are required. Every *value* check stays in the operation, and
 * deliberately so - `invalid_name`, `invalid_password`, `invalid_email` and
 * `invalid_role` are documented error codes clients switch on, and a
 * `minLength: 1` or a `format: email` here would pre-empt them with the
 * library's schema-derived 400 instead. The published schema is that much
 * looser than the hand-maintained one was; the wire behaviour is unchanged,
 * which is the trade this migration keeps making.
 *
 * TODO: move the value checks INTO the schema (`minLength: 1` on password,
 * `format: email`) once the frontend can read the library's 400 - it carries
 * a JSON pointer per issue, which is strictly better for a form than one
 * `error` code for the whole request. That is a frontend change first and a
 * backend change second, and it applies to every request body from here on,
 * not only this one. See PLAN.md.
 */
final readonly class NewUser implements ProvidesSchema
{
    /**
     * @param array<string>|null $roles
     */
    public function __construct(
        public string $username,
        public string $password,
        public string $firstName,
        public string $lastName,
        public ?array $roles = null,
        public ?string $email = null,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= ObjectSchema::create(
            properties: ObjectProperties::create(
                username: StringSchema::create(),
                password: StringSchema::create(),
                firstName: StringSchema::create(),
                lastName: StringSchema::create(),
                roles: Nullable::wrap(ArraySchema::create(
                    description: 'Role identifiers; short names are prefixed with `Neos.Neos:`. '
                        . 'Omitted = Neos default roles, `[]` = explicitly none.',
                    items: StringSchema::create(),
                )),
                email: Nullable::wrap(StringSchema::create()),
            ),
            required: ['username', 'password', 'firstName', 'lastName'],
        );
    }
}
