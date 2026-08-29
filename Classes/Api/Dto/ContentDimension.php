<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Api\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * One configured content dimension, with its label already translated.
 */
final readonly class ContentDimension implements ProvidesSchema
{
    public function __construct(
        public string $id,
        public string $label,
        // Nullable but always present, and declared before $values so the
        // serialized key order stays the one clients have always seen.
        public ?string $icon,
        public ContentDimensionValues $values,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
