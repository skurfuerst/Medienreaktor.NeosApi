<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

/**
 * Who is calling, as the Api classes see it: the identity behind the bearer
 * token, flattened to the four things an operation can reasonably ask about.
 *
 * Deliberately NOT the Flow account or the security context - an Api class
 * that wants roles, workspaces or content permissions goes through the
 * content repository and the policy layer like everything else. This is the
 * caller's identity, not an authorization API.
 *
 * Reaches an operation as an #[AuthContext] argument, which the library keeps
 * out of the request's public shape - so it needs no JSON schema of its own.
 */
final readonly class ApiCaller
{
    /**
     * @param array<string> $roles Flow role identifiers of the account
     * @param array<string> $scopes OAuth scopes the token was granted
     */
    public function __construct(
        public string $accountIdentifier,
        public array $roles,
        public array $scopes,
        public ?string $clientIdentifier = null,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
