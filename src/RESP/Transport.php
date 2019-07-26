<?php

namespace Gone\ReddShim\RESP;

use Gone\ReddShim\EchoLogger;
use Monolog\Logger;
use React\Socket;

class Transport
{
    /** @var Logger */
    protected $logger;
    /** @var Socket\ConnectionInterface */
    protected $client;
    /** @var Socket\ConnectionInterface */
    protected $server;

    public function __construct(
        EchoLogger $logger
    )
    {
        $this->logger = $logger;
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
            $this->client->getRemoteAddress(),
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
                $this->server->getRemoteAddress(),
                $this->client->getRemoteAddress()
            ));

        return $this;
    }

    protected function receiveClientMessage($data)
    {
        $success = $this->server->write($data);
        if($success){
            $this->logger->info(sprintf(
                "[%s] => %s",
                $this->client->getRemoteAddress(),
                trim($data)
            ));
        }else{
            $this->logger->crit(sprintf(
                "[%s] => %s [FAILED] ",
                $this->client->getRemoteAddress(),
                trim($data)
            ));
        }
    }

    protected function receiveServerMessage($data)
    {
        $success = $this->client->write($data);
        if($success){
            $this->logger->info(sprintf(
                "[%s] <= %s",
                $this->server->getRemoteAddress(),
                trim($data)
            ));
        }else{
            $this->logger->crit(sprintf(
                "[%s] <= %s [FAILED] ",
                $this->server->getRemoteAddress(),
                trim($data)
            ));
        }
    }

    protected function handleClientException(\Exception $e)
    {
        $this->logger->critical(sprintf(
            "[%s] ** %s",
            $this->client->getRemoteAddress(),
            $e->getMessage()
        ));
    }

    protected function endClient()
    {
        $this->logger->info(sprintf(
            "[%s] == EndClient",
            $this->client->getRemoteAddress()
        ));
    }

    protected function closeClient()
    {
        $this->logger->info(sprintf(
            "[%s] == CloseClient",
            $this->client->getRemoteAddress()
        ));
    }

    protected function handleServerException(\Exception $e)
    {
        $this->logger->critical(sprintf(
            "[%s] ** %s",
            $this->server->getRemoteAddress(),
            $e->getMessage()
        ));
    }

    protected function endServer()
    {
        $this->logger->info(sprintf(
            "[%s] == EndServer",
            $this->server->getRemoteAddress()
        ));
    }

    protected function closeServer()
    {
        $this->logger->info(sprintf(
            "[%s] == CloseServer",
            $this->server->getRemoteAddress()
        ));
    }
}