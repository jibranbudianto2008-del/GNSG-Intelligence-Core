<?php

declare(strict_types=1);

namespace GNSG\Http;

use InvalidArgumentException;
use RuntimeException;
use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Middleware\Pipeline;

final class Router
{
    private array $routes = [];

    public function add(
        string $method,
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        foreach ($middleware as $item) {
            if (!$item instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    'Route middleware must implement MiddlewareInterface.'
                );
            }
        }

        $method = strtoupper($method);

        if (!preg_match('/\A[A-Z]{1,20}\z/', $method)) {
            throw new InvalidArgumentException(
                'Invalid route HTTP method.'
            );
        }

        if (
            $path === ''
            || $path[0] !== '/'
            || strlen($path) > 4096
            || str_contains($path, '?')
            || str_contains($path, '#')
            || preg_match('/[\x00-\x20\x7F\\\\]/', $path) === 1
            || preg_match('/%(?![a-fA-F0-9]{2})/', $path) === 1
        ) {
            throw new InvalidArgumentException(
                'Invalid route path.'
            );
        }

        $segments = $path === '/'
            ? []
            : explode('/', substr($path, 1));

        $regexSegments = [];
        $parameterNames = [];
        $staticSegments = 0;

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException(
                    'Route path contains an empty segment.'
                );
            }

            if (
                preg_match(
                    '/\A\{([a-zA-Z_][a-zA-Z0-9_]*)\}\z/',
                    $segment,
                    $matches
                ) === 1
            ) {
                $name = $matches[1];

                if (in_array($name, $parameterNames, true)) {
                    throw new InvalidArgumentException(
                        'Duplicate route parameter name.'
                    );
                }

                $parameterNames[] = $name;

                $regexSegments[] = '(?P<' . $name . '>[^/]+)';

                continue;
            }

            if (
                str_contains($segment, '{')
                || str_contains($segment, '}')
            ) {
                throw new InvalidArgumentException(
                    'Invalid route parameter syntax.'
                );
            }

            $staticSegments++;
            $regexSegments[] = preg_quote($segment, '~');
        }

        $regex = '~\A/'
            . implode('/', $regexSegments)
            . '\z~D';

        foreach ($this->routes as $route) {
            if (
                $route['method'] === $method
                && $route['path'] === $path
            ) {
                throw new InvalidArgumentException(
                    'Duplicate route registration.'
                );
            }
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
            'static_segments' => $staticSegments,
            'parameter_names' => $parameterNames,
        ];

        return $this;
    }

    public function get(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function put(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    public function options(
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        return $this->add('OPTIONS', $path, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatches = [];

        foreach ($this->routes as $route) {
            $matches = [];

            if (
                preg_match(
                    $route['regex'],
                    $request->path(),
                    $matches
                ) !== 1
            ) {
                continue;
            }

            $parameters = [];

            foreach ($route['parameter_names'] as $name) {
                $parameters[$name] = $matches[$name];
            }

            $pathMatches[] = [
                'route' => $route,
                'parameters' => $parameters,
            ];
        }

        if ($pathMatches === []) {
            return Response::json(
                [
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'Route not found.',
                    ],
                ],
                404
            );
        }

        // Prefer routes with more static segments.
        usort(
            $pathMatches,
            static fn(array $a, array $b): int =>
                $b['route']['static_segments']
                <=> $a['route']['static_segments']
        );

        $allowedMethods = [];

        foreach ($pathMatches as $match) {
            $route = $match['route'];

            $allowedMethods[] = $route['method'];

            if ($route['method'] !== $request->method()) {
                continue;
            }

            $destination = static function (
                Request $request
            ) use ($route, $match): Response {
                $response = ($route['handler'])(
                    $request,
                    $match['parameters']
                );

                if (!$response instanceof Response) {
                    throw new RuntimeException(
                        'Route handler must return a Response instance.'
                    );
                }

                return $response;
            };

            if ($route['middleware'] === []) {
                $response = $destination($request);
            } else {
                $pipeline = new Pipeline();

                foreach ($route['middleware'] as $middleware) {
                    $pipeline->pipe($middleware);
                }

                $response = $pipeline->process(
                    $request,
                    $destination
                );
            }

            if (!$response instanceof Response) {
                throw new RuntimeException(
                    'Route handler must return a Response instance.'
                );
            }

            return $response;
        }

        $allowedMethods = array_values(
            array_unique($allowedMethods)
        );

        if (!in_array('OPTIONS', $allowedMethods, true)) {
            $allowedMethods[] = 'OPTIONS';
        }

        sort($allowedMethods);

        if ($request->method() === 'OPTIONS') {
            return new Response(
                '',
                204,
                ['Allow' => implode(', ', $allowedMethods)]
            );
        }

        return Response::json(
            [
                'error' => [
                    'code' => 'method_not_allowed',
                    'message' => 'HTTP method not allowed.',
                ],
            ],
            405,
            ['Allow' => implode(', ', $allowedMethods)]
        );
    }
}
