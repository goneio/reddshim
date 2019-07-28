<?php

namespace Gone\ReddShim\Rewrites;

use Gone\ReddShim\RESP\Transport;
use Gone\ReddShim\Server;
use Predis\Cluster\ClusterStrategy;
use Predis\Cluster\RedisStrategy;
use Predis\Response\Status;

class RequestRewriter
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

    public const FUNCTIONS_KEYS_AND_VALUES    = ['SET', 'MSET', ];
    public const FUNCTIONS_KEY_ONLY           = ['GET', 'MGET', ];
    public const FUNCTIONS_TAKE_ONE_ARGUMENT  = ['GET', 'SET', ];
    public const FUNCTIONS_RETURN_ONE_ARGUMENT = ['GET', ];
    public const FUNCTIONS_PLAYBACK_ALL_NODES = ['FLUSHALL', ];

    public function rewrite($function, $argumentString) : bool
    {
        $this->buckets = [];
        $this->releventServers = [];
        $function = strtoupper($function);
        \Kint::$max_depth = 3;
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $argumentString, $arguments);
        $arguments = $arguments[0];
        array_walk($arguments, function(&$a){
            $a = trim($a, "\"");
        });
        \Kint::dump($function, $argumentString, $arguments);

        /** @var Server[] $relevantServers */
        $relevantServers = [];
        $buckets = [];
        /** @var Status[] $responses */
        $responses = [];
        if(in_array($function, self::FUNCTIONS_KEYS_AND_VALUES)){
            foreach(array_chunk($arguments,2) as $chunk){
                list($key, $value) = $chunk;
                $hash = $this->calculateHash($key);
                $server = $this->transport->getServerByHash($hash, true);
                $buckets[$server->getConnection()->getRemoteAddress()][$hash][$key] = $value;
                $relevantServers[$server->getConnection()->getRemoteAddress()] = $server;
            }
        }elseif(in_array($function, self::FUNCTIONS_KEY_ONLY) || in_array($function, self::FUNCTIONS_PLAYBACK_ALL_NODES)){
            foreach($arguments as $key){
                $hash = $this->calculateHash($key);
                $server = $this->transport->getServerByHash($hash, true);
                $buckets[$server->getConnection()->getRemoteAddress()][$hash][] = $key;
                $relevantServers[$server->getConnection()->getRemoteAddress()] = $server;
            }
        }

        if(in_array($function, self::FUNCTIONS_PLAYBACK_ALL_NODES)){
            foreach($this->transport->getAllWritableServers() as $i => $server){
                $buckets[$server->getConnection()->getRemoteAddress()][$i][] = null;
                $relevantServers[$server->getConnection()->getRemoteAddress()] = $server;
            }
        }

        \Kint::dump($buckets);

        foreach($buckets as $address => $slots){
            foreach($slots as $slot => $data) {
                if(in_array($function, self::FUNCTIONS_TAKE_ONE_ARGUMENT)){
                    foreach($data as $k => $v) {
                        if(is_numeric($k)) {
                            $response = $relevantServers[$address]->getControlPlane()->$function($v);
                        }else{
                            $response = $relevantServers[$address]->getControlPlane()->$function($k, $v);
                        }
                        $responses[$address][] = $response;
                    }
                }else {
                    if (empty(array_filter($data))) {
                        $response = $relevantServers[$address]->getControlPlane()->$function();
                    }else{
                        $response = $relevantServers[$address]->getControlPlane()->$function($data);
                    }
                    $responses[$address][] = $response;
                }
            }
        }

        \Kint::dump($responses);

        // Check to see if any of the responses were of type Status
        $hasStatusResponse = false;
        foreach($responses as $bucket => $bucketOfResponses){
            foreach($bucketOfResponses as $response) {
                if ($response instanceof Status) {
                    $hasStatusResponse = true;
                }
            }
        }

        // If we have a Status, and they're all OK, return OK, otherwise, return the first deviation.
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
        }

        // Otherwise, return a merged set of responses.
        $finalResponse = call_user_func_array('array_merge', $responses);
        if(is_array(reset($finalResponse))) {
            $finalResponse = call_user_func_array('array_merge', $finalResponse);
        }
        \Kint::dump($finalResponse);
        if(in_array($function, self::FUNCTIONS_RETURN_ONE_ARGUMENT) && count($finalResponse) == 1){
            $finalResponse = reset($finalResponse);
        }
        \Kint::dump($finalResponse);
        $this->transport->sendClientMessage(
            is_array($finalResponse)
                ? $this->transport->createRespArray($finalResponse)
                : "+{$finalResponse}"
        );
        return true;

    }

}