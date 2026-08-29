<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

/**
 * The token authenticates somebody, but not somebody allowed to do THIS.
 *
 * Thrown by FlowAuthContextProvider when the operation's security requirement
 * names scopes the token does not carry. It is an exception rather than a
 * "no caller" answer because the library's AuthContextProvider can only say
 * null, and null means 401 - while a valid token lacking a scope is a 403
 * (RFC 6750 "insufficient_scope"). OpenApiDispatchController turns it into
 * that response.
 */
final class InsufficientScopeException extends \RuntimeException
{
    public function __construct(public readonly string $requiredScope)
    {
        parent::__construct(sprintf('This request requires the "%s" scope.', $requiredScope), 1783600001);
    }
}
