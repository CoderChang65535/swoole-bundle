<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Api;

use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Throwable;

final class ApiServerRequestHandler implements RequestHandlerInterface
{
    private $routes;

    public function __construct(ApiServerInterface $apiServer)
    {
        $this->routes = [
            '/healthz' => [
                ApiServerInterface::HTTP_METHOD_GET => [
                    'code' => 200,
                    'handler' => function () {
                        return ['ok' => true];
                    },
                ],
            ],
            '/api' => [
                ApiServerInterface::HTTP_METHOD_GET => [
                    'code' => 200,
                    'handler' => [$this, 'getRouteMap'],
                ],
            ],
            '/api/server' => [
                ApiServerInterface::HTTP_METHOD_GET => [
                    'code' => 200,
                    'handler' => [$apiServer, 'status'],
                ],
                ApiServerInterface::HTTP_METHOD_PATCH => [
                    'code' => 204,
                    'handler' => [$apiServer, 'reload'],
                ],
                ApiServerInterface::HTTP_METHOD_DELETE => [
                    'code' => 204,
                    'handler' => [$apiServer, 'shutdown'],
                ],
            ],
            '/api/server/metrics' => [
                ApiServerInterface::HTTP_METHOD_GET => [
                    'code' => 200,
                    'handler' => [$apiServer, 'metrics'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(Request $request, Response $response): void
    {
        try {
            [$method] = $this->parseRequestInfo($request);
            switch ($method) {
                case ApiServerInterface::HTTP_METHOD_HEAD:
                    $request->server['request_method'] = ApiServerInterface::HTTP_METHOD_GET;
                    [$statusCode] = $this->handleRequest($request);
                    $this->sendResponse($response, $statusCode);
                    break;
                case ApiServerInterface::HTTP_METHOD_GET:
                case ApiServerInterface::HTTP_METHOD_POST:
                case ApiServerInterface::HTTP_METHOD_PATCH:
                case ApiServerInterface::HTTP_METHOD_DELETE:
                    [$statusCode, $data] = $this->handleRequest($request);
                    $this->sendResponse($response, $statusCode, $data);

                    return;
                default:
                    $this->sendResponse($response, 405, [
                        'error' => \sprintf('Method "%s" is not supported. Supported ones are: %s.', $method, \implode(', ', ApiServerInterface::HTTP_METHODS)),
                    ]);

                    return;
            }
        } catch (Throwable $exception) {
            $this->sendResponse($response, 500, [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'trace' => \explode("\n", $exception->getTraceAsString()),
            ]);
        }
    }

    private function parseRequestInfo(Request $request): array
    {
        $method = \mb_strtoupper($request->server['request_method']);
        $path = \mb_strtolower(\rtrim($request->server['path_info'], '/'));
        $path = '' === $path ? '/' : $path;

        return [$method, $path];
    }

    private function handleRequest(Request $request): array
    {
        [$method, $path] = $this->parseRequestInfo($request);

        if (\array_key_exists($path, $this->routes)) {
            $route = $this->routes[$path];
            if (\array_key_exists($method, $route)) {
                $action = $route[$method];

                return [$action['code'], $action['handler']($request)];
            }

            return [405, [
                'error' => \sprintf('Method %s for route %s is not valid. Supported ones are: %s.', $method, $path, \implode(', ', \array_keys($route))),
            ]];
        }

        return [404, [
            'error' => \sprintf('Route %s does not exists.', $path),
            'routes' => $this->getRouteMap(),
        ]];
    }

    private function getRouteMap(): array
    {
        return \array_map(function (array $route): array {
            return \array_keys($route);
        }, $this->routes);
    }

    private function sendResponse(Response $response, int $statusCode = 200, ?array $data = []): void
    {
        if (empty($data) || 204 === $statusCode) {
            $response->status(200 === $statusCode ? 204 : $statusCode);
            $response->end();

            return;
        }

        $response->header('Content-Type', 'application/json');
        $response->status($statusCode);

        $options = \defined('JSON_THROW_ON_ERROR') ? \JSON_THROW_ON_ERROR : 0;
        $json = \json_encode($data, $options);

        // TODO: Drop on PHP 7.3 Migration
        if (!\defined('JSON_THROW_ON_ERROR') && false === $json) {
            throw new \RuntimeException(\json_last_error_msg(), \json_last_error());
        }

        $response->end($json);
    }
}
