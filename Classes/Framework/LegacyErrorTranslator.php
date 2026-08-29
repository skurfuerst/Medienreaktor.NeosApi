<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Framework;

use Neos\OpenApi\Problem\ProblemDocument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Keeps one error envelope on the wire while the library speaks another.
 *
 * The library answers its own four failures (400/401/404/405) as RFC 9457
 * "application/problem+json"; this API has always answered
 * {"error", "error_description"} and clients parse that. Until the API adopts
 * problem+json deliberately - a separate, announced change - every generated
 * failure is rewritten here, status code and hop-by-hop headers untouched.
 *
 * The 400's issue list survives as an "issues" member: it is richer than the
 * single sentence the old validation produced, and the envelope has always
 * allowed extra top-level detail fields.
 *
 * DocsController performs the matching rewrite on the SERVED document, so
 * what the spec advertises for these responses is what this emits.
 */
final readonly class LegacyErrorTranslator
{
    /**
     * The machine-readable codes the four generated failures map to. Chosen to
     * match what the hand-written controllers already emitted for the same
     * situations ("unauthorized" in particular is what requireScope() used).
     */
    private const CODES = [
        400 => 'invalid_request',
        401 => 'unauthorized',
        404 => 'not_found',
        405 => 'method_not_allowed',
    ];

    public function __construct(
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * A response as it should go out: a problem document becomes the legacy
     * envelope, anything else passes through untouched.
     */
    public function translate(ResponseInterface $response): ResponseInterface
    {
        if (!str_starts_with($response->getHeaderLine('Content-Type'), ProblemDocument::CONTENT_TYPE)) {
            return $response;
        }

        $problem = json_decode((string)$response->getBody(), true);
        if (!is_array($problem)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $body = [
            'error' => self::CODES[$status] ?? 'error',
            'error_description' => (string)($problem['detail'] ?? $problem['title'] ?? ''),
        ];
        if (($problem['issues'] ?? []) !== []) {
            $body['issues'] = $problem['issues'];
        }

        return $this->json($response, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function json(ResponseInterface $response, array $body): ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(
                (string)json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ));
    }
}
