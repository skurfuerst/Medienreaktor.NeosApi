<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * The `{"user": ...}` wrapper the single-user operations answer with.
 *
 * A wrapper rather than the user itself, as it has always been: it leaves room
 * to report something alongside the user later without changing the shape of
 * what is already there.
 */
final readonly class UserEnvelope implements ProvidesSchema
{
    public function __construct(
        public User $user,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
