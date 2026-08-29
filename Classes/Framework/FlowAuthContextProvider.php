<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Medienreaktor\NeosApi\Security\Authentication\Token\ApiBearerToken;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authentication\AuthenticationManagerInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Security\Exception\NoTokensAuthenticatedException;
use Neos\OpenApi\Http\AuthContextProvider;
use Neos\OpenApi\Spec\SecurityRequirementObject;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns the request's bearer token into the ApiCaller an operation receives.
 *
 * It authenticates first. In an MVC request the policy advice around the
 * action does that, but the library asks who is calling BEFORE it invokes the
 * operation - which is earlier than any advice can run, and is the right order:
 * an unauthenticated request should be answered with a challenge rather than by
 * a privilege check that happens to fail. Flow's authentication manager is
 * idempotent, so the advice on the Api method later finds the work already done.
 *
 * Two answers, two meanings, and the library only understands one of them:
 *  - null: no authenticated bearer token -> the library answers 401 with a
 *    WWW-Authenticate challenge
 *  - InsufficientScopeException: a valid token that was not granted a scope
 *    the operation requires -> 403 insufficient_scope, which the dispatch
 *    controller renders (see the exception for why it is not a null)
 *
 * This pair replaces AbstractApiController::requireScope(); the scopes now
 * live in the operation's own #[Operation(security: ...)] declaration, which
 * is also what publishes them in the document.
 */
#[Flow\Scope('singleton')]
final class FlowAuthContextProvider implements AuthContextProvider
{
    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly AuthenticationManagerInterface $authenticationManager,
    ) {
    }

    public function authContextFor(ServerRequestInterface $request, SecurityRequirementObject $requirement): ?object
    {
        $token = $this->authenticatedBearerToken();
        if ($token === null) {
            return null;
        }

        $account = $token->getAccount();
        $caller = new ApiCaller(
            accountIdentifier: $account?->getAccountIdentifier() ?? '',
            roles: $account === null ? [] : array_keys($account->getRoles()),
            scopes: $token->getScopes(),
            clientIdentifier: $token->getClientIdentifier(),
        );

        $this->requireScopes($caller, $requirement);

        return $caller;
    }

    private function authenticatedBearerToken(): ?ApiBearerToken
    {
        try {
            $this->authenticationManager->authenticate();
        } catch (AuthenticationRequiredException | NoTokensAuthenticatedException) {
            // "nobody could be authenticated" is an answer, not a failure: the
            // library turns a null caller into the 401 challenge.
            return null;
        }

        foreach ($this->securityContext->getAuthenticationTokensOfType(ApiBearerToken::class) as $token) {
            if ($token->isAuthenticated()) {
                return $token;
            }
        }

        return null;
    }

    /**
     * The requirement's alternatives are OR-ed (satisfying one is enough), the
     * scopes within one alternative are AND-ed. Reported failure is the first
     * missing scope of the first alternative - the same single-scope message
     * requireScope() produced, since every operation here names exactly one.
     */
    private function requireScopes(ApiCaller $caller, SecurityRequirementObject $requirement): void
    {
        $firstMissing = null;
        foreach ($requirement as $alternative) {
            $missing = null;
            foreach ($alternative as $scopes) {
                foreach ($scopes as $scope) {
                    if (!$caller->hasScope($scope)) {
                        $missing = $scope;
                        break 2;
                    }
                }
            }
            if ($missing === null) {
                return;
            }
            $firstMissing ??= $missing;
        }

        if ($firstMissing !== null) {
            throw new InsufficientScopeException($firstMissing);
        }
    }
}
