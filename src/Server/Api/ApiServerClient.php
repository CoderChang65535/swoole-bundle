<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Api;

use Assert\Assertion;
use K911\Swoole\Server\Config\Sockets;
use Swoole\Coroutine\Http\Client;

final class ApiServerClient implements ApiServerInterface
{
    private $serverSockets;

    /**
     * @var Client|null
     */
    private $client;

    public function __construct(Sockets $sockets)
    {
        $this->serverSockets = $sockets;
    }

    /**
     * Get Swoole HTTP Server status.
     *
     * @return array
     */
    public function status(): array
    {
        return $this->sendRequest('/api/server')['data'];
    }

    /**
     * Shutdown Swoole HTTP Server.
     */
    public function shutdown(): void
    {
        $this->sendRequest('/api/server', ApiServerInterface::HTTP_METHOD_DELETE);
    }

    /**
     * Reload Swoole HTTP Server workers.
     */
    public function reload(): void
    {
        $this->sendRequest('/api/server', ApiServerInterface::HTTP_METHOD_PATCH);
    }

    /**
     * Get Swoole HTTP Server metrics.
     *
     * @return array
     */
    public function metrics(): array
    {
        return $this->sendRequest('/api/metrics')['data'];
    }

    private function sendRequest(string $path, string $method = ApiServerInterface::HTTP_METHOD_GET, ?array $data = null): ?array
    {
        Assertion::inArray($method, ApiServerInterface::HTTP_METHODS, 'Method "%s" is not supported. Supported ones are: %s.');
        $client = $this->getClient();

        $client->setMethod($method);

        $headers = ['accept' => 'application/json'];
        if (!empty($data)) {
            $headers['content-type'] = 'application/json';

            $options = \defined('JSON_THROW_ON_ERROR') ? \JSON_THROW_ON_ERROR : 0;
            $json = \json_encode($data, $options);

            // TODO: Drop on PHP 7.3 Migration
            if (!\defined('JSON_THROW_ON_ERROR') && false === $json) {
                throw new \RuntimeException(\json_last_error_msg(), \json_last_error());
            }

            $client->setData($json);
        }

        $client->setHeaders($headers);
        $client->execute($path);

        return $this->resolveResponse($client);
    }

    private function resolveResponseData(Client $client): array
    {
        if (204 === $client->statusCode) {
            return [];
        }

        Assertion::keyExists($client->headers, 'content-type', 'Server response did not contain Content-Type.');
        Assertion::eq($client->headers['content-type'], 'application/json', 'Content-Type "%s" is not supported. Only "%s" is supported.');

        // TODO: Drop on PHP 7.3 Migration
        if (!\defined('JSON_THROW_ON_ERROR')) {
            $data = \json_decode($client->body, true);
            if (null === $data) {
                throw new \RuntimeException(\json_last_error_msg(), \json_last_error());
            }

            return $data;
        }

        return \json_decode($client->body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param Client $client
     *
     * @return array
     */
    private function resolveResponse(Client $client): array
    {
        $client->recv(1);

        if ($client->statusCode < 0) {
            switch ($client->statusCode) {
                case -1:
                    $error = 'Connection Failed';
                    break;
                case -2:
                    $error = 'Request Timeout';
                    break;
                case -3:
                    $error = 'Server Reset';
                    break;
                default:
                    $error = 'Unknown';
                    break;
            }

            throw new \RuntimeException($error, $client->errCode);
        }

        $response = [
            'cookies' => $client->cookies,
            'headers' => $client->headers,
            'statusCode' => $client->statusCode,
            'data' => $this->resolveResponseData($client),
        ];

        return $response;
    }

    private function newClient(): Client
    {
        Assertion::true($this->serverSockets->hasApiSocket(), 'Swoole HTTP Server is not configured properly. To access API trough HTTP interface, you must enable and provide proper address of configured API Server.');
        $apiSocket = $this->serverSockets->getApiSocket();

        return new Client($apiSocket->host(), $apiSocket->port(), $apiSocket->ssl());
    }

    private function getClient(): Client
    {
        if (null === $this->client) {
            $this->client = $this->newClient();
        }

        return $this->client;
    }

    public function __destruct()
    {
        if (null !== $this->client && $this->client->connected) {
            $this->client->close();
        }
    }
}
