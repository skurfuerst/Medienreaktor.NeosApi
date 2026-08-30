<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\NodeTypes\Response;

use Medienreaktor\NeosApi\Framework\Dto\LegacyError;
use Neos\OpenApi\Binding\TypeReference;
use Neos\OpenApi\Response\ApiResponse;
use Neos\OpenApi\Support\HttpStatusCode;
use Neos\OpenApi\Support\MediaTypeRange;

/**
 * 404 for a node type name that is well-formed but not configured.
 *
 * Same status, same body and same code as the controller's
 * throwJsonStatus(404, 'nodetype_not_found', ...) - only now the document says
 * so because the operation's return type does.
 */
final readonly class NodeTypeNotFound implements ApiResponse
{
    public static function statusCode(): HttpStatusCode
    {
        return HttpStatusCode::fromInteger(404);
    }

    public static function description(): string
    {
        return '`nodetype_not_found`';
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
        return new LegacyError('nodetype_not_found', 'The node type does not exist.');
    }
}
