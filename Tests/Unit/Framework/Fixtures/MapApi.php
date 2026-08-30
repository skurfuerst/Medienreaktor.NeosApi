<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Tests\Unit\Framework\Fixtures;

use Neos\OpenApi\Attributes\Operation;

/**
 * Compiled for real in EmptyMapResponseTest: what shapes the response is the
 * schema the compiler publishes for this operation's return type.
 */
class MapApi
{
    #[Operation(path: '/api/maps', method: 'GET', operationId: 'listMaps', allowAnonymous: true)]
    public function index(): Maps
    {
        return new Maps(entries: [], names: []);
    }
}
