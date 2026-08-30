<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 404 for a user id nothing resolves to. Note that a syntactically odd id
 * lands here too rather than on a 400: UserId accepts any string, so "does it
 * exist" is the only question actually being asked.
 */
final readonly class UserNotFound implements ApiResponse
{
    public function __construct(private string $userId)
    {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return '`user_not_found`';
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
        return new LegacyError('user_not_found', sprintf('No user with the id "%s" exists.', $this->userId));
    }
}
