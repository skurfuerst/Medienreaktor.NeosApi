<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures;

use Neos\OpenApi\Attributes\Operation;

/**
 * A minimal Api class, compiled for real in SpecMergerTest: the merger's job
 * is to reshape what the compiler produces, so a hand-written imitation of a
 * generated document would test the imitation instead.
 */
class GreetingApi
{
    #[Operation(path: '/api/greetings/{name}', method: 'GET', operationId: 'showGreeting', security: ['oauth2' => ['neos.read']])]
    public function show(Greeting $name): Greeting
    {
        return $name;
    }

    #[Operation(path: '/api/greetings', method: 'GET', operationId: 'listGreetings', allowAnonymous: true)]
    public function index(): Greeting
    {
        return Greeting::fromString('hello');
    }
}
