<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * One of a user's authentication accounts. A Neos user usually has exactly
 * one, on the backend provider, but the model allows several and the listing
 * has always shown all of them.
 */
final readonly class Account implements ProvidesSchema
{
    public function __construct(
        public string $accountIdentifier,
        public string $authenticationProvider,
        public bool $active,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
