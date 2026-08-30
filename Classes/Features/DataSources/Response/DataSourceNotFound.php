<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 404 for an identifier no registered DataSourceInterface implementation
 * claims. The description carries the identifier, exactly as the controller
 * spelled it.
 */
final readonly class DataSourceNotFound implements ApiResponse
{
    public function __construct(
        private string $identifier,
    ) {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return '`unknown_data_source`';
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
        return new LegacyError('unknown_data_source', sprintf('No data source with identifier "%s" exists.', $this->identifier));
    }
}
