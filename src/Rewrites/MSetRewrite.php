<?php

namespace Gone\ReddShim\Rewrites;

use Gone\ReddShim\RESP\Transport;
use Gone\ReddShim\Server;
use Predis\Cluster\ClusterStrategy;
use Predis\Cluster\RedisStrategy;
use Predis\Response\Status;

class MSetRewrite
{
    /** @var Transport */
    protected $transport;
    /** @var ClusterStrategy */
    protected $clusterStrategy;

    protected $releventServers;
    protected $buckets;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
        $this->clusterStrategy = new RedisStrategy();
    }

    private function calculateHash(string $key) : int
    {
        return $this->clusterStrategy->getSlotByKey($key);
    }

    public function rewrite($function, $arguments) : bool
    {
        $this->buckets = [];
        $this->releventServers = [];
        $function = strtoupper($function);
        \Kint::$max_depth = 3;
        $arguments = explode(" ", $arguments);

        #\Kint::dump($function, $arguments);

        /** @var Server[] $releventServers */
        $releventServers = [];
        $buckets = [];
        /** @var Status[] $responses */
        $responses = [];
        switch($function){
            case 'SET':
            case 'MSET':
                // Data goes K V K V K V
                foreach(array_chunk($arguments,2) as $chunk){
                    list($key, $value) = $chunk;
                    $hash = $this->calculateHash($key);
                    $server = $this->transport->getServerByHash($hash, true);
                    $buckets[$server->getConnection()->getRemoteAddress()][$hash][$key] = $value;
                    $releventServers[$server->getConnection()->getRemoteAddress()] = $server;
                }
                break;
            case 'GET':
            case 'MGET':
                // Data goes K K K
                foreach($arguments as $key){
                    $hash = $this->calculateHash($key);
                    $server = $this->transport->getServerByHash($hash, true);
                    $buckets[$server->getConnection()->getRemoteAddress()][$hash][] = $key;
                    $releventServers[$server->getConnection()->getRemoteAddress()] = $server;
                }
                break;
        }

        #\Kint::dump($buckets);

        foreach($buckets as $address => $slots){
            foreach($slots as $slot => $data) {
                $response = $releventServers[$address]->getControlPlane()->$function($data);
                $responses[$address][] = $response;
            }
        }

        \Kint::dump($responses);

        $hasStatusResponse = false;
        foreach($responses as $bucket => $bucketOfResponses){
            foreach($bucketOfResponses as $response) {
                if ($response instanceof Status) {
                    $hasStatusResponse = true;
                }
            }
        }

        if($hasStatusResponse) {
            foreach($responses as $bucket => $bucketOfResponses){
                foreach($bucketOfResponses as $response) {
                    if ($response->getPayload() != 'OK') {
                        $this->transport->sendClientMessage($response->getPayload());
                        return false;
                    }
                }
            }
            $this->transport->sendClientMessage("+OK");
            return true;
        }else{
            #\Kint::dump($responses);
            $finalResponse = call_user_func_array('array_merge', $responses);
            #\Kint::dump($finalResponse);
            $finalResponse = call_user_func_array('array_merge', $finalResponse);
            #\Kint::dump($finalResponse);
            $this->transport->sendClientMessage($this->transport->createRespArray($finalResponse));
            return true;
        }
    }

}