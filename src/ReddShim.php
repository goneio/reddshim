<?php
namespace Gone\ReddShim;

use Gone\AppCore\App;
use Gone\AppCore\Redis\Redis;
use Gone\AppCore\Services\EnvironmentService;
use Gone\ReddShim\RESP;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket;

class ReddShim extends App {
    protected $isSessionsEnabled = false;

    public function setupDependencies(): void
    {
        parent::setupDependencies();

        $this->container->offsetUnset(Redis::class);
        $this->container->offsetUnset('MonologStreamHandler');

        /** @var EnvironmentService $environmentService */
        $environmentService = $this->container->get(EnvironmentService::class);
        $environmentService->set('MONOLOG_FORMAT','%channel%.%level_name%: %message%');
    }

    /**
     * @todo refactor this function into some other class
     */
    public function initSocket(){
        /** @var EnvironmentService $environmentService */
        $environmentService = $this->container->get(EnvironmentService::class);

        $loop = EventLoopFactory::create();
        /** @var LoggerInterface $logger */
        #$logger = $this->getApp()->getContainer()->get(Logger::class);
        $logger = new EchoLogger();
        $logger->info("Starting socket server");
        $socket = new Socket\Server('0.0.0.0:6379', $loop);

        $configuredRedises = explode(",", $environmentService->get("REDIS_CONFIGURED", "DEFAULT"));
        array_walk($configuredRedises, function(&$item){ $item = trim($item); });
        $configuredRedises = array_flip($configuredRedises);

        foreach($configuredRedises as $name => &$redis){
            $redis = [
            ];
            if($environmentService->isSet("REDIS_{$name}_MASTERS")){
                $redis['masters'] = explode(",", $environmentService->get("REDIS_{$name}_MASTERS"));
                array_walk( $redis['masters'], function(&$item){ $item = trim($item); });
            }
            if($environmentService->isSet("REDIS_{$name}_SLAVES")){
                $redis['slaves'] = explode(",", $environmentService->get("REDIS_{$name}_SLAVES"));
                array_walk( $redis['slaves'], function(&$item){ $item = trim($item); });
            }
            if($environmentService->isSet("REDIS_{$name}")){
                $redis['solo'] = explode(",", $environmentService->get("REDIS_{$name}"));
                array_walk( $redis['solo'], function(&$item){ $item = trim($item); });
                $redis['solo'] = reset($redis['solo']);
            }
        }

        $socket->on('connection', function (Socket\ConnectionInterface $client) use ($loop, $logger, $configuredRedises) {
            (new RESP\Transport($logger, $loop))
                ->attachClient($client)
                ->setConnectionOptions($configuredRedises);
        });

        $loop->run();
    }
}
