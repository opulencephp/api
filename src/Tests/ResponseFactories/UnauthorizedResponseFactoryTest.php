<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2018 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Tests\ResponseFactories;

use Opulence\Api\ResponseFactories\UnauthorizedResponseFactory;
use Opulence\Net\Http\HttpStatusCodes;

/**
 * Tests the unauthorized response factory
 */
class UnauthorizedResponseFactoryTest extends ResponseFactoryTestCase
{
    public function testCreatingResponseUsesCorrectStatusCode(): void
    {
        $responseFactory = new UnauthorizedResponseFactory();
        $response = $responseFactory->createResponse($this->createBasicRequestContext());
        $this->assertEquals(HttpStatusCodes::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}