<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 409 for a username somebody already has.
 */
final readonly class UserExists implements ApiResponse
{
    public function __construct(private string $username)
    {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(409);
    }

    public static function description(): string
    {
        return '`user_exists`';
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
        return new LegacyError('user_exists', sprintf('A user with the username "%s" already exists.', $this->username));
    }
}
