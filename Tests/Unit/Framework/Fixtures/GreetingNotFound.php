<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * A failure an operation raises deliberately, answered in this API's own error
 * envelope - the case SpecMerger has to fold into the document's `Error`.
 */
final readonly class GreetingNotFound implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return '`greeting_not_found`';
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
        return new LegacyError('greeting_not_found', 'No such greeting.');
    }
}
