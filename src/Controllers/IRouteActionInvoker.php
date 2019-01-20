<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (c) 2019 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Api\Controllers;

use Exception;
use Opulence\Net\Http\IHttpRequestMessage;
use Opulence\Net\Http\IHttpResponseMessage;

/**
 * Defines the interface for route action invokers to implement
 */
interface IRouteActionInvoker
{
    /**
     * Invokes a route action
     *
     * @param callable $routeActionDelegate The route action delegate to invoke
     * @param IHttpRequestMessage $request The current request
     * @param array $routeVariables The route variables
     * @return IHttpResponseMessage The response
     * @throws Exception Thrown if there was any error processing the request
     */
    public function invokeRouteAction(
        callable $routeActionDelegate,
        IHttpRequestMessage $request,
        array $routeVariables
    ): IHttpResponseMessage;
}
