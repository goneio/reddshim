<?php
namespace Gone\ReddShim;

use Gone\AppCore\App;
use Gone\AppCore\Redis\Redis;
use Gone\AppCore\Services\EnvironmentService;
use Gone\ReddShim\RESP;
use Monolog\Logger;
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
        #/** @var Logger $logger */
        #$logger = $this->getApp()->getContainer()->get(Logger::class);
        $logger = new EchoLogger();
        $logger->info("Starting socket server");
        $socket = new Socket\Server('0.0.0.0:6379', $loop);

        \Kint::dump($environmentService->keys());
        $configuredRedises = explode(",", $environmentService->get("REDIS_CONFIGURED", "DEFAULT"));
        array_walk($configuredRedises, function(&$item){ $item = trim($item); });
        $configuredRedises = array_flip($configuredRedises);
        \Kint::dump($configuredRedises);
        foreach($configuredRedises as $name => &$redis){
            $redis = [
                'masters' => [],
                'slaves' => [],
            ];
            if($environmentService->isSet("REDIS_{$name}_MASTERS")){
                $redis['masters'] = explode(",", $environmentService->get("REDIS_{$name}_MASTERS"));
                array_walk( $redis['masters'], function(&$item){ $item = trim($item); });
            }
            if($environmentService->isSet("REDIS_{$name}_SLAVES")){
                $redis['slaves'] = explode(",", $environmentService->get("REDIS_{$name}_SLAVES"));
                array_walk( $redis['slaves'], function(&$item){ $item = trim($item); });

            }
        }

        \Kint::dump($configuredRedises);
        exit;
        // @todo make this smorter.
        $upstreamRedis = "tcp://redis-solo:6379";
        #$upstreamRedis = "tcp://echo:3333";

        $socket->on('connection', function (Socket\ConnectionInterface $client) use ($loop, $logger, $upstreamRedis) {
            $logger->info(sprintf(
                "Connecting to %s",
                $upstreamRedis
            ));
            $client->pause();

            (new Socket\Connector($loop))
                ->connect($upstreamRedis)->then(function(Socket\ConnectionInterface $server) use ($loop, $logger, $client) {
                    $server->pause();
                    (new RESP\Transport($logger))
                        ->attachClient($client)
                        ->attachServer($server)
                        ->resume()
                    ;
                });
        });

        $loop->run();
    }
}
