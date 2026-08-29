<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Api\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * One configured value of a content dimension.
 *
 * specializationDepth is what lets a client render the hierarchy from a flat
 * list: values arrive depth-first, each specialization directly after its
 * generalization.
 */
final readonly class ContentDimensionValue implements ProvidesSchema
{
    public function __construct(
        public string $value,
        public string $label,
        public int $specializationDepth,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
