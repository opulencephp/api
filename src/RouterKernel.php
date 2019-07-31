<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/api/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Api;

use Aphiria\Api\Controllers\Controller;
use Aphiria\Api\Controllers\ControllerRequestHandler;
use Aphiria\Api\Controllers\IRouteActionInvoker;
use Aphiria\Api\Controllers\RouteActionInvoker;
use Aphiria\Middleware\AttributeMiddleware;
use Aphiria\Middleware\IMiddleware;
use Aphiria\Middleware\MiddlewarePipelineFactory;
use Aphiria\Net\Http\ContentNegotiation\IContentNegotiator;
use Aphiria\Net\Http\Handlers\IRequestHandler;
use Aphiria\Net\Http\HttpException;
use Aphiria\Net\Http\HttpStatusCodes;
use Aphiria\Net\Http\IHttpRequestMessage;
use Aphiria\Net\Http\IHttpResponseMessage;
use Aphiria\Net\Http\Response;
use Aphiria\Routing\Matchers\IRouteMatcher;
use Aphiria\Routing\Matchers\RouteMatchingResult;
use Aphiria\Routing\Middleware\MiddlewareBinding;
use Aphiria\Routing\RouteAction;
use Closure;
use InvalidArgumentException;

/**
 * Defines the kernel request handler that performs routing
 */
class RouterKernel implements IRequestHandler
{
    /** @var IRouteMatcher The route matcher */
    private IRouteMatcher $routeMatcher;
    /** @var IDependencyResolver The dependency resolver */
    private IDependencyResolver $dependencyResolver;
    /** @var IContentNegotiator The content negotiator */
    private IContentNegotiator $contentNegotiator;
    /** @var MiddlewarePipelineFactory The middleware pipeline factory */
    private ?MiddlewarePipelineFactory $middlewarePipelineFactory;
    /** @var IRouteActionInvoker The route action invoker */
    private IRouteActionInvoker $routeActionInvoker;

    /**
     * @param IRouteMatcher $routeMatcher The route matcher
     * @param IDependencyResolver $dependencyResolver The dependency resolver
     * @param IContentNegotiator $contentNegotiator The content negotiator
     * @param MiddlewarePipelineFactory|null $middlewarePipelineFactory THe middleware pipeline factory
     * @param IRouteActionInvoker|null $routeActionInvoker The route action invoker
     */
    public function __construct(
        IRouteMatcher $routeMatcher,
        IDependencyResolver $dependencyResolver,
        IContentNegotiator $contentNegotiator,
        MiddlewarePipelineFactory $middlewarePipelineFactory = null,
        IRouteActionInvoker $routeActionInvoker = null
    ) {
        $this->routeMatcher = $routeMatcher;
        $this->dependencyResolver = $dependencyResolver;
        $this->contentNegotiator = $contentNegotiator;
        $this->middlewarePipelineFactory = $middlewarePipelineFactory ?? new MiddlewarePipelineFactory();
        $this->routeActionInvoker = $routeActionInvoker ?? new RouteActionInvoker($this->contentNegotiator);
    }

    /**
     * @inheritdoc
     */
    public function handle(IHttpRequestMessage $request): IHttpResponseMessage
    {
        $matchingResult = $this->matchRoute($request);
        $controller = $routeActionDelegate = null;
        $this->createController($matchingResult->route->action, $controller, $routeActionDelegate);
        $controllerRequestHandler = new ControllerRequestHandler(
            $controller,
            $routeActionDelegate,
            $matchingResult->routeVariables,
            $this->contentNegotiator,
            $this->routeActionInvoker
        );
        $middlewarePipeline = $this->middlewarePipelineFactory->createPipeline(
            $this->createMiddlewareFromBindings($matchingResult->route->middlewareBindings),
            $controllerRequestHandler
        );

        return $middlewarePipeline->handle($request);
    }

    /**
     * Creates a controller from a route action
     *
     * @param RouteAction $routeAction The route action to create the controller from
     * @param Controller $controller The "out" parameter that will contain the controller
     * @param callable $routeActionDelegate The "out" parameter that will contain the route action delegate
     * @throws DependencyResolutionException Thrown if the controller could not be resolved
     */
    private function createController(
        RouteAction $routeAction,
        ?Controller &$controller,
        ?callable &$routeActionDelegate
    ): void {
        if ($routeAction->usesMethod()) {
            $controller = $this->dependencyResolver->resolve($routeAction->className);
            $routeActionDelegate = [$controller, $routeAction->methodName];

            if (!is_callable($routeActionDelegate)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Controller method %s::%s() does not exist',
                        $routeAction->className,
                        $routeAction->methodName
                    )
                );
            }
        } else {
            $controller = new Controller();
            $routeActionDelegate = Closure::bind($routeAction->closure, $controller, Controller::class);
        }

        if (!$controller instanceof Controller) {
            throw new InvalidArgumentException(
                sprintf('Controller %s does not extend %s', get_class($controller), Controller::class)
            );
        }
    }

    /**
     * Creates middleware instances from middleware bindings
     *
     * @param MiddlewareBinding[] $middlewareBindings The list of middleware bindings to create instances from
     * @return IMiddleware[] The middleware instances
     * @throws DependencyResolutionException Thrown if the middleware could not be resolved
     */
    private function createMiddlewareFromBindings(array $middlewareBindings): array
    {
        $middlewareList = [];

        foreach ($middlewareBindings as $middlewareBinding) {
            $middleware = $this->dependencyResolver->resolve($middlewareBinding->className);

            if (!$middleware instanceof IMiddleware) {
                throw new InvalidArgumentException(
                    sprintf('Middleware %s does not implement %s', get_class($middleware), IMiddleware::class)
                );
            }

            if ($middleware instanceof AttributeMiddleware) {
                $middleware->setAttributes($middlewareBinding->attributes);
            }

            $middlewareList[] = $middleware;
        }

        return $middlewareList;
    }

    /**
     * Gets the matching route for the input request
     *
     * @param IHttpRequestMessage $request The current request
     * @return RouteMatchingResult The route matching result
     * @throws HttpException Thrown if there was no matching route, or if the request was invalid for the matched route
     */
    private function matchRoute(IHttpRequestMessage $request): RouteMatchingResult
    {
        $uri = $request->getUri();
        $matchingResult = $this->routeMatcher->matchRoute($request->getMethod(), $uri->getHost(), $uri->getPath());

        if (!$matchingResult->matchFound) {
            if ($matchingResult->methodIsAllowed === null) {
                throw new HttpException(HttpStatusCodes::HTTP_NOT_FOUND, "No route found for {$request->getUri()}");
            }

            $response = new Response(HttpStatusCodes::HTTP_METHOD_NOT_ALLOWED);
            $response->getHeaders()->add('Allow', $matchingResult->allowedMethods);

            throw new HttpException($response, 'Method not allowed');
        }

        return $matchingResult;
    }
}