<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\Command;

use Assert\Assertion;
use K911\Swoole\Server\Api\ApiServerInterface;
use K911\Swoole\Server\Config\Socket;
use K911\Swoole\Server\Config\Sockets;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ServerStatusCommand extends Command
{
    private $apiServer;
    private $sockets;
    private $parameterBag;

    public function __construct(
        Sockets $sockets,
        ApiServerInterface $apiServer,
        ParameterBagInterface $parameterBag
    ) {
        $this->apiServer = $apiServer;
        $this->sockets = $sockets;
        $this->parameterBag = $parameterBag;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Get current status of the Swoole HTTP Server by querying running API Server.')
            ->addOption('api.host', null, InputOption::VALUE_REQUIRED, 'API Server listens on this host.', $this->parameterBag->get('swoole.http_server.api.host'))
            ->addOption('api.port', null, InputOption::VALUE_REQUIRED, 'API Server listens on this port.', $this->parameterBag->get('swoole.http_server.api.port'));
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Assert\AssertionFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->prepareClientConfiguration($input);

        \go(function () use ($io): void {
//            $status = $this->apiServer->status();
//            dump($status);
            $metrics = $this->apiServer->metrics();
//            dump($metrics);

            $date = \DateTimeImmutable::createFromFormat(DATE_ATOM, $metrics['date']);
            Assertion::isInstanceOf($date, \DateTimeImmutable::class);
            $runningSeconds = $date->getTimestamp() - $metrics['server']['start_time'];

            $idleWorkers = $metrics['server']['idle_worker_num'];
            $workers = $metrics['server']['worker_num'];

            $io->success('Fetched metrics');

            $io->table([
                'Metric', 'Quantity', 'Unit',
            ], [
                ['Requests', $metrics['server']['request_count'], '1'],
                ['Up time', $runningSeconds, 'Seconds'],
                ['Active connections', $metrics['server']['connection_num'], '1'],
                ['Accepted connections', $metrics['server']['accept_count'], '1'],
                ['Closed connections', $metrics['server']['close_count'], '1'],
                ['Active workers', $workers - $idleWorkers, '1'],
                ['Idle workers', $idleWorkers, '1'],
            ]);
        });
        \swoole_event_wait();

        return 0;
    }

    /**
     * @param InputInterface $input
     *
     * @throws \Assert\AssertionFailedException
     */
    protected function prepareClientConfiguration(InputInterface $input): void
    {
        $port = $input->getOption('api.port');
        $host = $input->getOption('api.host');

        Assertion::numeric($port, 'Port must be a number.');
        Assertion::string($host, 'Host must be a string.');

        $this->sockets->changeApiSocket(new Socket($host, (int) $port));
    }
}
