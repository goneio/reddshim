<?php
namespace Gone\ReddShim;


use Predis\Client as PredisClient;
use React\Socket\ConnectionInterface;

class Server {
    /** @var PredisClient */
    protected $controlPlane;
    /** @var ConnectionInterface */
    protected $connection;
    /** @var bool */
    protected $isWritable;

    public function __construct(ConnectionInterface $connection, bool $isWritable)
    {
        $this->connection = $connection;
        $this->controlPlane = new PredisClient($connection->getRemoteAddress());
        $this->isWritable = $isWritable;
    }

    /**
     * @return PredisClient
     */
    public function getControlPlane(): PredisClient
    {
        return $this->controlPlane;
    }

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function isWritable() : bool
    {
        return $this->isWritable;
    }

}