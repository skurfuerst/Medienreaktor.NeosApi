<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 400 for a `node` parameter that is not a decodable node address, or that
 * belongs to a different content repository than this API serves - the two
 * cases the codec distinguishes, sharing one code as they always have.
 */
final readonly class InvalidNodeAddress implements ApiResponse
{
    public function __construct(
        private string $description,
    ) {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(400);
    }

    public static function description(): string
    {
        return '`invalid_node_address`';
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
        return new LegacyError('invalid_node_address', $this->description);
    }
}
