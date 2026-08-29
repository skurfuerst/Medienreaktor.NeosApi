<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework;

use GuzzleHttp\Psr7\HttpFactory;
use Medienreaktor\NeosApi\Framework\LegacyErrorTranslator;
use Neos\Flow\Tests\UnitTestCase;
use Neos\OpenApi\Problem\ProblemDocument;

/**
 * Clients parse {"error", "error_description"}; the library emits RFC 9457.
 * These pin the bridge between them - what a client sees is the whole point,
 * so every failure the library can generate is covered.
 */
class LegacyErrorTranslatorTest extends UnitTestCase
{
    private LegacyErrorTranslator $translator;

    private HttpFactory $httpFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpFactory = new HttpFactory();
        $this->translator = new LegacyErrorTranslator($this->httpFactory);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function generatedFailureProvider(): iterable
    {
        yield 'rejected input' => [400, 'invalid_request'];
        yield 'no credentials' => [401, 'unauthorized'];
        yield 'no such path' => [404, 'not_found'];
        yield 'wrong method' => [405, 'method_not_allowed'];
    }

    /**
     * @test
     * @dataProvider generatedFailureProvider
     */
    public function aProblemDocumentBecomesTheLegacyEnvelope(int $status, string $expectedCode): void
    {
        $translated = $this->translator->translate($this->problem($status, 'Bad Request', 'Something specific'));

        self::assertSame($status, $translated->getStatusCode());
        self::assertSame('application/json', $translated->getHeaderLine('Content-Type'));
        self::assertSame(
            ['error' => $expectedCode, 'error_description' => 'Something specific'],
            json_decode((string)$translated->getBody(), true)
        );
    }

    /**
     * A 400 lists every value it rejected. That detail is richer than the
     * single sentence the controllers produced, and the envelope has always
     * allowed extra top-level fields, so it is kept rather than flattened.
     *
     * @test
     */
    public function rejectedValuesSurviveAsIssues(): void
    {
        $issues = [['code' => 'minLength', 'message' => 'too short', 'pointer' => '/query/limit']];
        $translated = $this->translator->translate($this->problem(400, 'Bad Request', 'Nope', $issues));

        self::assertSame($issues, json_decode((string)$translated->getBody(), true)['issues']);
    }

    /**
     * @test
     */
    public function aResponseWithoutIssuesDoesNotAnnounceAnEmptyList(): void
    {
        $translated = $this->translator->translate($this->problem(401, 'Unauthorized', 'No credentials'));

        self::assertArrayNotHasKey('issues', json_decode((string)$translated->getBody(), true));
    }

    /**
     * Everything an operation answers itself - a success, an ApiResponse
     * branch - is already in this API's own shapes and must not be touched.
     *
     * @test
     */
    public function anOrdinaryResponsePassesThroughUnchanged(): void
    {
        $response = $this->httpFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->httpFactory->createStream('{"dimensions":[]}'));

        self::assertSame('{"dimensions":[]}', (string)$this->translator->translate($response)->getBody());
    }

    /**
     * The challenge is what tells a client HOW to authenticate, so it has to
     * survive a rewrite of the body it travels with.
     *
     * @test
     */
    public function headersOfTheOriginalResponseAreKept(): void
    {
        $response = $this->problem(401, 'Unauthorized', 'No credentials')->withHeader('WWW-Authenticate', 'Bearer');

        self::assertSame('Bearer', $this->translator->translate($response)->getHeaderLine('WWW-Authenticate'));
    }

    /**
     * @param list<array<string, string>> $issues
     */
    private function problem(int $status, string $title, string $detail, array $issues = []): \Psr\Http\Message\ResponseInterface
    {
        $body = ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail];
        if ($issues !== []) {
            $body['issues'] = $issues;
        }

        return $this->httpFactory->createResponse($status)
            ->withHeader('Content-Type', ProblemDocument::CONTENT_TYPE)
            ->withBody($this->httpFactory->createStream((string)json_encode($body)));
    }
}
