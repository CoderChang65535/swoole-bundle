<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Api;

use K911\Swoole\Server\HttpServer;
use K911\Swoole\Server\HttpServerConfiguration;

/**
 * API Server for Swoole HTTP Server. If enabled, is running on another port, than regular server.
 * Used to control original Swoole HTTP Server.
 */
final class ApiServer implements ApiServerInterface
{
    private $server;
    private $serverConfiguration;

    public function __construct(HttpServer $server, HttpServerConfiguration $serverConfiguration)
    {
        $this->server = $server;
        $this->serverConfiguration = $serverConfiguration;
    }

    /**
     * {@inheritdoc}
     */
    public function metrics(): array
    {
        return [
            'date' => (new \DateTimeImmutable('now'))->format(\DATE_ATOM),
            'server' => $this->server->metrics(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        $this->server->shutdown();
    }

    /**
     * {@inheritdoc}
     */
    public function reload(): void
    {
        $this->server->reload();
    }

    /**
     * {@inheritdoc}
     */
    public function status(): array
    {
        $server = $this->server->getServer();

        $listeners = [];
        foreach ($this->server->getListeners() as $listener) {
            $listeners[] = [
                'host' => \property_exists($listener, 'host') ? $listener->host : '-',
                'port' => $listener->port,
            ];
        }

        return [
            'date' => \date(\DATE_ATOM),
            'server' => [
                'host' => $server->host,
                'port' => $server->port,

                'runningMode' => $this->serverConfiguration->getRunningMode(),
                'processes' => [
                    'master' => [
                        'pid' => $server->master_pid,
                    ],
                    'manager' => [
                        'pid' => $server->manager_pid,
                    ],
                    'worker' => [
                        'id' => $server->worker_id,
                        'pid' => $server->worker_pid,
                    ],
                ],
                'settings' => $server->setting,
                'listeners' => $listeners,
            ],
        ];
    }
}
