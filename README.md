<h1>API</h1>

> **Note:** This library is still in development.

<h1>Table of Contents</h1>

1. [Introduction](#introduction)
    1. [Installation](#installation)
2. [Controllers](#controllers)
    1. [Parameter Resolution](#parameter-resolution)
    2. [Controller Dependencies](#controller-dependencies)
    3. [Closure Controllers](#closure-controllers)
3. [Middleware](#middleware)
4. [Request Handlers](#request-handlers)
    1. [Exception Handling](#exception-handling)

<h1 id="introduction">Introduction</h1>

The API library makes it simpler for you to get your application's API up and running.  It acts as the entry point into your application, and takes advantage of several of Opulence's other libraries to handle things like route matching and content negotiation.

<h2 id="installation">Installation</h2>

You can install this library by including the following package name in your _composer.json_:

```
"opulence/api": "1.0.*"
```

<h1 id="controllers">Controllers</h1>

Your controllers can either extend `Controller` or be a [`Closure`](#closure-controllers).  It comes packed with helper methods to make your code less cluttered.  For example, let's say you wanted to return an array of `User` objects from your API.  Simple:

```php
class UserController extends Controller
{
    // ...
    
    public function getUserById(int $userId): User
    {
        return $this->userRepository->getById($userId);
    }
}
```

Opulence will create a 200 response whose body is the serialized return value.  It uses content negotiation to determine the media type to serialize to (eg JSON).  You can also be a bit more explicit and return a response yourself.  For example, the following controller method is functionally identical to the previous example:

```php
class UserController extends Controller
{
    // ...
    
    public function getUserById(int $userId): IHttpResponseMessage
    {
        $user = $this->userRepository->getById($userId);

        return $this->ok($user);
    }
}
```

The `ok()` helper method uses a `ResponseFactory` to build a response using the current [request context](#request-context).  You can pass in a POPO as the response body, and the factory will use content negotiation to determine how to serialize it.

The following helper methods come bundled with `Controller`:

* `badRequest()`
* `conflict()`
* `created()`
* `forbidden()`
* `found()`
* `internalServerError()`
* `movedPermanently()`
* `noContent()`
* `notFound()`
* `ok()`
* `unauthorized()`

If your controller method has a `void` return type, a 204 "No Content" response will be created automatically.

<h3 id="headers">Headers</h3>

Setting headers is simple, too:

```php
use Opulence\Net\Http\HttpHeaders;

class UserController extends Controller
{
    // ...
    
    public function getUserById(int $userId): IHttpResponseMessage
    {
        $user = $this->userRepository->getUserById($userId);
        $headers = new HttpHeaders();
        $headers->add('Cache-Control', 'no-cache');
        
        return $this->ok($user, $headers);
    }
}
```

<h3 id="request-context">Request Context</h3>

To grab context about the current request (such as the request object itself or the matched route), you can grab the `RequestContext` from your controller:

```php
class UserController extends Controller
{
    // ...

    public function getAllUsers(): IHttpResponseMessage
    {
        $request = $this->requestContext->getRequest();
        $matchedRoute = $this->requestContext->getMatchedRoute();
        
        // ...
    }
}
```

<h2 id="parameter-resolution">Parameter Resolution</h2>

Your controller methods will frequently need to do things like deserialize the request body or read route/query string values.  Opulence simplifies this process enormously by allowing your method signatures to be expressive.  For example, if you specify any object type hint, it will automatically deserialize the request body to any POPO:

```php
class UserController extends Controller
{
    // ...
    
    public function createUser(User $user): IHttpResponseMessage
    {
        $this->userRepository->addUser($user);
        
        return $this->created();
    }
}
```

This works for any media type (eg JSON) that you've registered to your <a href="https://github.com/opulencephp/net#content-negotiation" target="_blank">content negotiator</a>.

Need a route path variable?  Just type-hint it with a scalar type hint:

```php
class UserController extends Controller
{
    // ...
    
    public function getUserById(int $userId): IHttpResponseMessage
    {
        $user = $this->userRepository->getUserById($userId);
        
        return $this->ok($user);
    }
}
```

You can even grab values from the query string using scalar type-hints:

```php
class UserController extends Controller
{
    // ...
    
    public function getAllUsers(bool $includeDeletedUsers): IHttpResponseMessage
    {
        $users = $this->userRepository->getAllUsers($includeDeletedUsers);
        
        return $this->ok($users);
    }
}
```

Opulence will first scan for matching scalar values by name in your route variables, and then, if no match is found, the query string.  Multiple scalar parameters are supported.  It also gracefully handles nullable values and parameters with default values.

<h3 id="request-body-arrays">Request Body Arrays</h3>

Request bodies might contain an array of values.  Because PHP doesn't support generics or typed arrays, you cannot use type-hints alone to deserialize arrays of values.  However, it's still easy to do:

```php
class UserController extends Controller
{
    public function createManyUsers(): IHttpResponseMessage
    {
        $users = $this->readBodyAsArrayOf(User::class);
        $this->userRepository->addManyUsers($users);
        
        return $this->ok();
    }
}
```

<h2 id="controller-dependencies">Controller Dependencies</h2>

The API library provides support for auto-wiring your controllers.  In other words, it can scan your controllers' constructors for dependencies, resolve them, and then instantiate your controllers with those dependencies.  Dependency resolvers simply need to implement `IDependencyResolver`.  To make it easy for users of Opulence's DI container, you can use `ContainerDependencyResolver`.

Once you've instantiated your dependency resolver, pass it into your [request handler](#request-handlers) for auto-wiring.

<h2 id="closure-controllers">Closure Controllers</h2>

Sometimes, a controller class is overkill for a route that does very little.  In this case, you can use a `Closure` when defining your routes:

```php
 $routes->map('GET', 'ping')
    ->toClosure(function () {
        return $this->ok();
    });
```

Here's the cool part - Opulence will bind an instance of `Controller` to your closure, which means you can use [all the methods](#controllers) available inside of `Controller` via `$this`.

<h1 id="middleware">Middleware</h1>

Todo

<h1 id="request-handlers">Request Handlers</h1>

A request handler simply takes in an HTTP request and returns a response.  It is capable of matching a route and sending the request and response through [middleware](#middleware) to the [controller](#controllers).

Configuring your API is easy - you just need to set up a few things:

* <a href="https://github.com/opulencephp/router#basic-usage" target="_blank">Routes</a>
* <a href="https://github.com/opulencephp/net#content-negotiation" target="_blank">Content negotiator</a>
* [Dependency resolver](#controller-dependencies)

Handling a request from beginning to end is simple:

```php
use Opulence\Api\Handlers\ControllerRequestHandler;
use Opulence\Net\Http\Formatting\ResponseWriter;
use Opulence\Net\Http\RequestFactory;

// Assume your route matcher, dependency resolver, and content negotiator are already set
$requestHandler = new ControllerRequestHandler(
    $routeMatcher,
    $dependencyResolver,
    $contentNegotiator
);
$request = RequestFactory::createRequestFromSuperglobals($_SERVER);
$response = $requestHandler->handle($request);
(new ResponseWriter)->writeResponse($response);
```

<h2 id="exception-handling">Exception Handling</h2>

Todo