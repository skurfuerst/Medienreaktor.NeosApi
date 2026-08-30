<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework\Dto;

use Neos\JsonSchema\ProvidesSchema;
use Neos\JsonSchema\Schema;
use Neos\Schematic\Discovery\AutoDiscoveringSchema;

/**
 * The error envelope this API has always emitted, as a response body type.
 *
 * The library speaks RFC 9457 for the failures it generates itself, and
 * {@see \Medienreaktor\NeosApi\Framework\LegacyErrorTranslator} rewrites those
 * on the way out. The failures an operation raises *deliberately* - a missing
 * node type, an unknown workspace - are not the library's, so they are
 * returned as an ApiResponse carrying this, and pass through the translator
 * untouched because they are already what clients expect.
 *
 * {@see \Medienreaktor\NeosApi\Framework\SpecMerger} points the published
 * document's references at the hand-maintained `Error` schema, so the document
 * keeps naming one error type. Both go away together when this API adopts
 * problem+json.
 */
final readonly class LegacyError implements ProvidesSchema
{
    public function __construct(
        /**
         * Stable machine-readable code, e.g. `nodetype_not_found`.
         */
        public string $error,
        public string $error_description,
    ) {
    }

    public static function schema(): Schema
    {
        static $schema = null;
        return $schema ??= AutoDiscoveringSchema::analyze(self::class);
    }
}
