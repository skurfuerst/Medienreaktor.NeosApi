<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\DataSources\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 500 for a data source that threw. The exception text is deliberately not
 * in the body: data sources are arbitrary third-party code and their messages
 * may leak internals. It is logged instead.
 */
final readonly class DataSourceFailed implements ApiResponse
{
    public function __construct(
        private string $identifier,
    ) {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(500);
    }

    public static function description(): string
    {
        return '`data_source_failed`';
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
        return new LegacyError('data_source_failed', sprintf('Data source "%s" failed to execute.', $this->identifier));
    }
}
