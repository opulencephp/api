<?php

/*
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/api/blob/master/LICENSE.md
 */

namespace Aphiria\Api\Tests\Exceptions;

use Aphiria\Api\Exceptions\FatalThrowableError;
use ErrorException;
use InvalidArgumentException;
use ParseError;
use PHPUnit\Framework\TestCase;
use TypeError;

class FatalThrowableErrorTest extends TestCase
{
    public function throwableProvider(): array
    {
        return [
            [new ParseError],
            [new TypeError],
            [new InvalidArgumentException],
        ];
    }

    /**
     * @dataProvider throwableProvider
     */
    public function testConstructor($throwable): void
    {
        $throwableError = new FatalThrowableError($throwable);
        $this->assertInstanceOf(ErrorException::class, $throwableError);
    }
}