<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework;

use GuzzleHttp\Psr7\ServerRequest;
use Medienreaktor\NeosApi\Framework\ApiCaller;
use Medienreaktor\NeosApi\Framework\FlowAuthContextProvider;
use Medienreaktor\NeosApi\Framework\InsufficientScopeException;
use Medienreaktor\NeosApi\Security\Authentication\Token\ApiBearerToken;
use Neos\Flow\Security\Authentication\AuthenticationManagerInterface;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Tests\UnitTestCase;
use Neos\OpenApi\Spec\SecurityRequirementObject;

/**
 * Scope enforcement moved out of AbstractApiController::requireScope() and
 * into here, so this is where "the token may not do that" is now decided.
 * The distinction these pin down is the one the library cannot express: a
 * null caller means 401, and a caller lacking a scope means 403.
 */
class FlowAuthContextProviderTest extends UnitTestCase
{
    /**
     * @test
     */
    public function anUnauthenticatedRequestHasNoCaller(): void
    {
        $provider = $this->provider(null);

        self::assertNull($provider->authContextFor(new ServerRequest('GET', '/api/dimensions'), SecurityRequirementObject::scheme('oauth2')));
    }

    /**
     * Flow raises when nothing could be authenticated; the library expects an
     * answer, and answers a null with the 401 challenge itself.
     *
     * @test
     */
    public function aFailedAuthenticationIsAnAnswerRatherThanAnError(): void
    {
        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);
        $authenticationManager->method('authenticate')->willThrowException(new AuthenticationRequiredException());

        $provider = $this->provider(null, $authenticationManager);

        self::assertNull($provider->authContextFor(new ServerRequest('GET', '/api/dimensions'), SecurityRequirementObject::scheme('oauth2')));
    }

    /**
     * @test
     */
    public function anAuthenticatedTokenBecomesTheCaller(): void
    {
        $provider = $this->provider($this->token(['neos.read'], 'apitest'));

        $caller = $provider->authContextFor(
            new ServerRequest('GET', '/api/dimensions'),
            SecurityRequirementObject::scopes('oauth2', ['neos.read'])
        );

        self::assertInstanceOf(ApiCaller::class, $caller);
        self::assertSame('apitest', $caller->clientIdentifier);
        self::assertTrue($caller->hasScope('neos.read'));
    }

    /**
     * @test
     */
    public function aTokenWithoutTheRequiredScopeIsRefused(): void
    {
        $provider = $this->provider($this->token(['neos.media']));

        $this->expectException(InsufficientScopeException::class);
        $this->expectExceptionMessage('This request requires the "neos.read" scope.');

        $provider->authContextFor(
            new ServerRequest('GET', '/api/dimensions'),
            SecurityRequirementObject::scopes('oauth2', ['neos.read'])
        );
    }

    /**
     * Scopes named together all have to be granted - narrowing is the only
     * thing a scope can do, so a partial match is no match.
     *
     * @test
     */
    public function scopesNamedTogetherAllHaveToBeGranted(): void
    {
        $provider = $this->provider($this->token(['neos.read']));

        $this->expectException(InsufficientScopeException::class);
        $this->expectExceptionMessage('This request requires the "neos.write" scope.');

        $provider->authContextFor(
            new ServerRequest('POST', '/api/commands'),
            SecurityRequirementObject::all(['oauth2' => ['neos.read', 'neos.write']])
        );
    }

    /**
     * An operation that accepts anonymous callers states that as an
     * alternative with nothing to satisfy, so a token that satisfies none of
     * the others still gets through - with whatever scopes it has.
     *
     * @test
     */
    public function anAnonymouslyReachableOperationRefusesNobody(): void
    {
        $provider = $this->provider($this->token([]));

        $caller = $provider->authContextFor(
            new ServerRequest('GET', '/api/openapi.json'),
            SecurityRequirementObject::scopes('oauth2', ['neos.read'])->orAnonymously()
        );

        self::assertInstanceOf(ApiCaller::class, $caller);
    }

    /**
     * @param array<string> $scopes
     */
    private function token(array $scopes, ?string $clientIdentifier = null): ApiBearerToken
    {
        $token = new ApiBearerToken();
        $token->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
        $token->setScopes($scopes);
        if ($clientIdentifier !== null) {
            $token->setClientIdentifier($clientIdentifier);
        }

        return $token;
    }

    private function provider(?ApiBearerToken $token, ?AuthenticationManagerInterface $authenticationManager = null): FlowAuthContextProvider
    {
        $securityContext = $this->getMockBuilder(SecurityContext::class)->disableOriginalConstructor()->getMock();
        $securityContext->method('getAuthenticationTokensOfType')->willReturn($token === null ? [] : [$token]);

        return new FlowAuthContextProvider(
            $securityContext,
            $authenticationManager ?? $this->createMock(AuthenticationManagerInterface::class)
        );
    }
}
