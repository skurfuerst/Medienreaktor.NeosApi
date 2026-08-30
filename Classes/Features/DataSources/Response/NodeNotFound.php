<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 404 for a well-formed `node` address that resolves to nothing in the
 * account's subgraph.
 */
final readonly class NodeNotFound implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return '`node_not_found`';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(LegacyError::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public function body(): LegacyError
    {
        return new LegacyError('node_not_found', 'The node given as data source context does not exist.');
    }
}
