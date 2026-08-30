<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Dimensions\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * The values of one dimension, in configuration order (depth-first).
 */
final readonly class ContentDimensionValues implements ProvidesSchema
{
    /**
     * @var array<ContentDimensionValue>
     */
    public array $values;

    public function __construct(ContentDimensionValue ...$values)
    {
        $this->values = $values;
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
