<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Api;

interface ApiServerInterface
{
    public const HTTP_METHOD_HEAD = 'HEAD';
    public const HTTP_METHOD_GET = 'GET';
    public const HTTP_METHOD_POST = 'POST';
    public const HTTP_METHOD_PATCH = 'PATCH';
    public const HTTP_METHOD_DELETE = 'DELETE';

    public const HTTP_METHODS = [
        self::HTTP_METHOD_HEAD,
        self::HTTP_METHOD_GET,
        self::HTTP_METHOD_POST,
        self::HTTP_METHOD_PATCH,
        self::HTTP_METHOD_DELETE,
    ];

    /**
     * Get Swoole HTTP Server status.
     *
     * @return array
     */
    public function status(): array;

    /**
     * Shutdown Swoole HTTP Server.
     */
    public function shutdown(): void;

    /**
     * Reload Swoole HTTP Server workers.
     */
    public function reload(): void;

    /**
     * Get Swoole HTTP Server metrics.
     *
     * @return array
     */
    public function metrics(): array;
}
