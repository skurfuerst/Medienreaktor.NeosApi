<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Dimensions\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * The configured dimensions, ordered by priority.
 */
final readonly class ContentDimensions implements ProvidesSchema
{
    /**
     * @var array<ContentDimension>
     */
    public array $dimensions;

    public function __construct(ContentDimension ...$dimensions)
    {
        $this->dimensions = $dimensions;
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
