<?php

namespace Gone\ReddShim\RESP;

use Gone\ReddShim\EchoLogger;
use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\Socket;

class Transport
{
    /** @var Logger */
    protected $logger;
    /** @var Socket\ConnectionInterface */
    protected $client;
    /** @var Socket\ConnectionInterface */
    protected $server;
    /** @var Socket\ConnectionInterface[] */
    protected $connections = [];
    /** @var array */
    protected $connectionOptions = [];

    /** @var LoopInterface */
    protected $loop;

    public function __construct(
        EchoLogger $logger,
        LoopInterface $loop
    )
    {
        $this->logger = $logger;
        $this->loop = $loop;
    }

    /**
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     * @return Transport
     */
    public function setLogger(Logger $logger): Transport
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return Socket\ConnectionInterface
     */
    public function getClient(): Socket\ConnectionInterface
    {
        return $this->client;
    }

    /**
     * @param Socket\ConnectionInterface $client
     * @return Transport
     */
    public function setClient(Socket\ConnectionInterface $client): Transport
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return Socket\ConnectionInterface
     */
    public function getServer(): Socket\ConnectionInterface
    {
        return $this->server;
    }

    /**
     * @param Socket\ConnectionInterface $server
     * @return Transport
     */
    public function setServer(Socket\ConnectionInterface $server): Transport
    {
        $this->server = $server;
        return $this;
    }

    /**
     * @return array
     */
    public function getConnectionOptions(): array
    {
        return $this->connectionOptions;
    }

    /**
     * @param array $connectionOptions
     * @return Transport
     */
    public function setConnectionOptions(array $connectionOptions): Transport
    {
        $this->connectionOptions = $connectionOptions;
        return $this;
    }

    public function attachClient(Socket\ConnectionInterface $client): self
    {
        $this->client = $client;
        $this->client->on('data', \Closure::fromCallable([$this, 'receiveClientMessage']));
        $this->client->on('error', \Closure::fromCallable([$this, 'handleClientException']));
        $this->client->on('end', \Closure::fromCallable([$this, 'endClient']));
        $this->client->on('close', \Closure::fromCallable([$this, 'closeClient']));

        $this->logger->info(sprintf(
            "[%s] => %s",
            $this->getClientRemoteAddress(),
            "Connected"
        ));

        return $this;
    }

    public function resume() : self
    {
        $this->logger->info("Resuming C&S connections");
        $this->client->resume();
        $this->server->resume();
    }

    public function attachServer(Socket\ConnectionInterface $server) : self
    {
        $this->server = $server;
        $this->server->on('data', \Closure::fromCallable([$this, 'receiveServerMessage']));
        $this->server->on('error', \Closure::fromCallable([$this, 'handleServerException']));
        $this->server->on('end', \Closure::fromCallable([$this, 'endServer']));
        $this->server->on('close', \Closure::fromCallable([$this, 'closeServer']));

        $this->logger
            ->info(sprintf(
                "Connected to %s on behalf of %s",
                $this->getServerRemoteAddress(),
                $this->getClientRemoteAddress()
            ));

        return $this;
    }

    protected function receiveClientMessage($data)
    {
        $parsedData = $this->parseClientMessage($data);
        if($this->server || count($this->connections) > 0){
            $success = $this->server->write($data);
            if($success){
                $this->logger->info(sprintf(
                    "[%s] => %s",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            }else{
                $this->logger->crit(sprintf(
                    "[%s] => %s [FAILED] ",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            }
        }else{
            $parsedData = $this->parseClientMessage($data);
            $this->logger->info(sprintf(
                "[%s NOCONN] => %s",
                $this->getClientRemoteAddress(),
                $parsedData
            ));

        }
    }

    protected function parseClientMessage($data): ?string
    {
        $prefix = substr($data, 0,1);
        switch($prefix){
            case '*':
                return $this->parseClientRespArray(substr($data, 1));
                break;
            case '+':
                return substr($data, 1);
            default:
                return "Unhandled RESP prefix: {$prefix}";
        }
    }

    protected function parseClientRespArray(string $respArray) : string
    {
        $output = [];
        $respArray = explode("\r\n", trim($respArray));
        $words = array_chunk(array_slice($respArray,1), 2);
        foreach($words as $word){
            list($length, $buf) = $word;
            if(stripos($buf, " ")){
                $output[] = "\"{$buf}\"";
            }else{
                $output[] = $buf;
            }
        }
        $output = implode(" ", $output);
        $this->parseClientCommand($output);
        return trim($output);
    }

    protected function parseClientCommand($commandString){

        @list($command, $payload) = explode(" ", $commandString, 2);

        switch($command){
            case 'REDDSHIM_SELECT':
                $this->clientConnectRequestToServer($payload);
                break;
            default:
                // @todo sensible error
        }
    }

    protected function clientConnectRequestToServer($connectionRequest)
    {
        $this->client->pause();
        $target = $this->getConnectionOptions()[$connectionRequest];
        $this->connectServer($target);
    }

    public function connectServer($target)
    {
        if(count($this->connections) > 0){
            return;
        }
        $this->connections = [];
        if($target['solo']){
            $this->logger->info(sprintf(
                "Connecting to %s",
                $target['solo']
            ));
            $scope = $this;
            (new Socket\Connector($this->loop))
                ->connect($target['solo'])->then(function(Socket\ConnectionInterface $server) use ($scope) {
                    $scope->connections[] = $server;
                    $scope->attachServer($server);
                    $scope->client->resume();
                    $this->client->write("+OK\r\n");
                });
        }
    }

    protected function receiveServerMessage($data)
    {
        if($this->client->isWritable()) {
            $success = $this->client->write($data);
            $parsedData = $this->parseClientMessage($data);
            if ($success) {
                $this->logger->info(sprintf(
                    "[%s] <= %s",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            } else {
                $this->logger->crit(sprintf(
                    "[%s] <= %s [FAILED] ",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            }
        }
    }

    protected function handleClientException(\Exception $e)
    {
        $this->logger->critical(sprintf(
            "[%s] ** %s",
            $this->getClientRemoteAddress(),
            $e->getMessage()
        ));
    }

    protected function endClient()
    {
        #$this->logger->info(sprintf(
        #    "[%s] == EndClient",
        #    $this->getClientRemoteAddress()
        #));
    }

    protected function closeClient()
    {
        $this->logger->info(sprintf(
            "[%s] == CloseClient",
            $this->getClientRemoteAddress()
        ));
    }

    protected function handleServerException(\Exception $e)
    {
        $this->logger->critical(sprintf(
            "[%s] ** %s",
            $this->getServerRemoteAddress(),
            $e->getMessage()
        ));
    }

    protected function endServer()
    {
        $this->logger->info(sprintf(
            "[%s] == EndServer",
            $this->getServerRemoteAddress()
        ));
    }

    protected function closeServer()
    {
        $this->logger->info(sprintf(
            "[%s] == CloseServer",
            $this->getServerRemoteAddress()
        ));
    }

    protected function getClientRemoteAddress() : string
    {
        $host = parse_url($this->client->getRemoteAddress());
        return isset($host['host']) && isset($host['port'])
            ? "{$host['host']}:{$host['port']}"
            : "UNKNOWN";
    }

    protected function getServerRemoteAddress() : string
    {
        $host = parse_url($this->server->getRemoteAddress());
        return isset($host['host']) && isset($host['port'])
            ? "{$host['host']}:{$host['port']}"
            : "UNKNOWN";
    }
}