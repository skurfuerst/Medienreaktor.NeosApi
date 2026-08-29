<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\JsonSchema\StringSchema;
use Neos\Schematic\Schematic;

final readonly class Greeting implements ProvidesSchema
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return Schematic::instantiate(self::class, $value);
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= StringSchema::create(minLength: 1);
    }
}
