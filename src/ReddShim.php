<?php
namespace Gone\ReddShim;

use Gone\AppCore\App;
use Gone\AppCore\Redis\Redis;
use Monolog\Logger;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket;

class ReddShim extends App {
    protected $isSessionsEnabled = false;

    public function setupDependencies(): void
    {
        parent::setupDependencies();

        $this->container->offsetUnset(Redis::class);
    }

    public function initSocket(){
        $loop = EventLoopFactory::create();
        /** @var Logger $logger */
        $logger = $this->getApp()->getContainer()->get(Logger::class);
        $logger->addInfo("Starting socket server");
        echo "twiddlywoo";
        $socket = new Socket\Server('0.0.0.0:6379', $loop);

        $socket->on('connection', function (Socket\ConnectionInterface $connection) {
            $connection->write("Hello " . $connection->getRemoteAddress() . "!\n");
            $connection->write("Welcome to this amazing server!\n");
            $connection->write("Here's a tip: don't say anything.\n");

            $connection->on('data', function ($data) use ($connection) {
                $connection->close();
            });
        });

        $loop->run();
    }
}