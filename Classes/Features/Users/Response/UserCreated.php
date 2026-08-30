<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users\Response;

use Medienreaktor\NeosApi\Features\Users\Dto\UserEnvelope;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 201 with the created user.
 *
 * A success that is not a 200 has to be an ApiResponse: the library gives the
 * plain success branch of a return type the status 200, and this endpoint has
 * always answered 201.
 */
final readonly class UserCreated implements ApiResponse
{
    public function __construct(private UserEnvelope $user)
    {
    }

    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(201);
    }

    public static function description(): string
    {
        return 'The created user.';
    }

    public static function bodyType(): TypeReference
    {
        return TypeReference::of(UserEnvelope::class);
    }

    public static function contentType(): MediaTypeRange
    {
        return MediaTypeRange::fromString('application/json');
    }

    public function body(): UserEnvelope
    {
        return $this->user;
    }
}
