<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * A role an administrator may put on a user - one entry of the role picker's
 * catalog.
 */
final readonly class AssignableRole implements ProvidesSchema
{
    public function __construct(
        public string $identifier,
        public string $label,
        public string $packageKey,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
