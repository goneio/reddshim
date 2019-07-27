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
    protected $servers;
    /** @var Socket\ConnectionInterface[] */
    protected $connections = [];
    /** @var array */
    protected $connectionOptions = [];

    /** @var LoopInterface */
    protected $loop;
    protected $username;
    protected $password;

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
     * @param Socket\ConnectionInterface $server
     * @return Transport
     */
    public function setServer(Socket\ConnectionInterface $server): Transport
    {
        $this->server = $server;
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

    protected function getClientRemoteAddress(): string
    {
        $host = parse_url($this->client->getRemoteAddress());
        return isset($host['host']) && isset($host['port'])
            ? "{$host['host']}:{$host['port']}"
            : "UNKNOWN";
    }

    public function resume(): self
    {
        $this->logger->info("Resuming C&S connections");
        $this->client->resume();
        $this->server->resume();
    }

    /**
     * @return Socket\ConnectionInterface[]
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * @param Socket\ConnectionInterface[] $connections
     * @return Transport
     */
    public function setConnections(array $connections): Transport
    {
        $this->connections = $connections;
        return $this;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * @param LoopInterface $loop
     * @return Transport
     */
    public function setLoop(LoopInterface $loop): Transport
    {
        $this->loop = $loop;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     * @return Transport
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     * @return Transport
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getServer() : Socket\ConnectionInterface
    {
        if($this->server) {
            return $this->server;
        }else{
            \Kint::dump($this->servers);
            return $this->servers['masters'][array_rand($this->servers['masters'])];
        }
    }

    protected function receiveClientMessage($data)
    {
        $parsedData = $this->parseClientMessage($data);
        if ($this->server || count($this->connections) > 0) {
            $success = $this->getServer()->write($data);
            if ($success) {
                $this->logger->info(sprintf(
                    "[%s] => %s",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            } else {
                $this->logger->crit(sprintf(
                    "[%s] => %s [FAILED] ",
                    $this->getClientRemoteAddress(),
                    $parsedData
                ));
            }
        } else {
            $this->logger->info(sprintf(
                "[%s NOCONN] => %s",
                $this->getClientRemoteAddress(),
                $parsedData
            ));

        }
    }

    protected function parseClientMessage($data): ?string
    {
        $prefix = substr($data, 0, 1);

        switch ($prefix) {
            case '*':
                return $this->parseClientRespArray(substr($data, 1));
                break;
            case '+':
                return substr($data, 1);
            default:
                return $data;
        }
    }

    protected function parseClientRespArray(string $respArray): string
    {
        $output = [];
        $respArray = explode("\r\n", trim($respArray));
        $words = array_chunk(array_slice($respArray, 1), 2);
        foreach ($words as $word) {
            // @todo implement length validation.
            list($length, $buf) = $word;
            if (stripos($buf, " ")) {
                $output[] = "\"{$buf}\"";
            } else {
                $output[] = $buf;
            }
        }
        $output = implode(" ", $output);
        $this->parseClientCommand($output);
        return trim($output);
    }

    protected function parseClientCommand($commandString)
    {
        @list($command, $payload) = explode(" ", $commandString, 2);

        switch ($command) {
            case 'AUTH':
                $server = $this->clientConnectAuth($payload);
                $this->clientConnectRequestToServer($server);
                break;
            default:
        }
    }

    protected function clientConnectRequestToServer($connectionRequest)
    {
        $this->client->pause();
        $target = $this->getConnectionOptions()[$connectionRequest];
        if($target){
            $this->connectServer($connectionRequest, $target);
        }else{
            $this->client->write("+ERR No such server: {$connectionRequest}\r\n");
            $this->client->close();
        }
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

    public function connectServer(string $connectionRequest, array $target)
    {
        if (count($this->connections) > 0) {
            return;
        }
        $this->connections = [];
        $scope = $this;

        if (isset($target['solo'])) {
            $this->logger->info(sprintf(
                "Connecting to %s (%s)",
                $connectionRequest,
                $target['solo']
            ));
            (new Socket\Connector($this->loop))
                ->connect($target['solo'])->then(function (Socket\ConnectionInterface $server) use ($scope) {
                    $scope->connections[] = $server;
                    $scope->attachServer($server);
                    $scope->client->resume();
                    $this->client->write("+OK\r\n");
                });
        }elseif(isset($target['masters']) || isset($target['slaves'])){
            $this->logger->info(sprintf(
                "Connecting to %s (%d write, %d read)",
                $connectionRequest,
                count($target['masters']),
                count($target['slaves'])
            ));
            $targetServerCount = count($target['masters']) + count($target['slaves']);
            foreach($target['masters'] as $master) {
                $scope->connections[] = [];
                (new Socket\Connector($this->loop))
                    ->connect($master)->then(function (Socket\ConnectionInterface $server) use ($scope, $targetServerCount) {
                        $scope->connections[] = $server;
                        $scope->attachServerMaster($server, true);
                        if(count($this->connections) == $targetServerCount){
                            $scope->client->resume();
                            $this->client->write("+OK\r\n");
                        }
                    });
            }
            foreach($target['slaves'] as $slave) {
                $scope->connections[] = [];
                (new Socket\Connector($this->loop))
                    ->connect($slave)->then(function (Socket\ConnectionInterface $server) use ($scope, $targetServerCount) {
                        $scope->connections[] = $server;
                        $scope->attachServerSlave($server);
                        if(count($this->connections) == $targetServerCount){
                            $scope->client->resume();
                            $this->client->write("+OK\r\n");
                        }
                    });
            }
        }else{
            \Kint::dump($target);
            $errorMessage = sprintf(
                "Connecting has to %s failed, neither 'solo' or 'cluster' modes configured.",
                $connectionRequest
            );
            $this->logger->crit($errorMessage);
            $this->sendClientError($errorMessage);
            $this->client->end();
        }
    }

    public function sendClientMessage($message)
    {
        $this->logger->crit(sprintf(
            "[%s] <= %s ",
            $this->getClientRemoteAddress(),
            $message
        ));
        return $this->client->write($message);
    }

    public function sendClientError($message)
    {
        return $this->sendClientMessage("-{$message}\r\n");
    }

    public function attachServer(Socket\ConnectionInterface $server): self
    {
        $server->on('data', \Closure::fromCallable([$this, 'receiveServerMessage']));
        $server->on('error', \Closure::fromCallable([$this, 'handleServerException']));
        $server->on('end', \Closure::fromCallable([$this, 'endServer']));
        $server->on('close', \Closure::fromCallable([$this, 'closeServer']));

        $this->logger
            ->info(sprintf(
                "Connected to %s on behalf of %s",
                $server->getRemoteAddress(),
                $this->getClientRemoteAddress()
            ));

        $this->server = $server;

        return $this;
    }

    public function attachServerClusterMode(bool $isWritable, Socket\ConnectionInterface $server) : self
    {
        $server->on('data', \Closure::fromCallable([$this, 'receiveServerMessage']));
        $server->on('error', \Closure::fromCallable([$this, 'handleServerException']));
        $server->on('end', \Closure::fromCallable([$this, 'endServer']));
        $server->on('close', \Closure::fromCallable([$this, 'closeServer']));

        $this->logger
            ->info(sprintf(
                "Connected to %s on behalf of %s",
                $server->getRemoteAddress(),
                $this->getClientRemoteAddress()
            ));

        $this->servers[$isWritable ? 'masters' : 'slaves'][] = $server;

        return $this;
    }

    public function attachServerMaster(Socket\ConnectionInterface $server) : self
    {
        return $this->attachServerClusterMode(true, $server);
    }

    public function attachServerSlave(Socket\ConnectionInterface $server) : self
    {
        return $this->attachServerClusterMode(false, $server);
    }

    protected function getServerRemoteAddress(): string
    {
        $host = parse_url($this->server->getRemoteAddress());
        return isset($host['host']) && isset($host['port'])
            ? "{$host['host']}:{$host['port']}"
            : "UNKNOWN";
    }

    protected function clientConnectAuth($payload)
    {
        list($server, $username, $password) = explode(":", $payload);
        $this->setUsername($username)
            ->setPassword($password);
        // @todo user validation goes here.
        return $server;
    }

    protected function receiveServerMessage($data)
    {
        if ($this->client->isWritable()) {
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
}