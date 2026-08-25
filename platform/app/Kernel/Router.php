<?php
namespace Onay\App\Kernel;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array, middleware:string[]}> */
    private array $routes = [];

    public function get(string $pattern, array|callable $handler, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array|callable $handler, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array|callable $handler, array $middleware): self
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware');

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $result = $middleware::handle($request);
                if ($result instanceof Response) {
                    return $result;
                }
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                return (new $class())->{$method}($request, ...array_values($params));
            }

            return $handler($request, ...array_values($params));
        }

        return Response::html(View::render('front/404', [], 'layout/app'), 404);
    }

    /** {id} gibi yer tutuculari yakalar; yalnizca sayi ve slug kabul edilir. */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        if (!str_contains($pattern, '{')) {
            return null;
        }

        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[A-Za-z0-9_-]+)', $pattern);

        return preg_match('#^' . $regex . '$#', $path, $matches) === 1
            ? array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)
            : null;
    }
}
