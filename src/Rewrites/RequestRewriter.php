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

    public const FUNCTIONS_KEYS_AND_VALUES    = ['SET', 'MSET', 'HMSET', 'APPEND', ];
    public const FUNCTIONS_KEY_ONLY           = ['GET', 'MGET', 'HMGET', ];
    public const FUNCTIONS_KEYS_FIELDS_AND_VALUES = ['HMGET', 'HMSET', ];
    public const FUNCTIONS_TAKE_ONE_ARGUMENT  = ['GET', 'SET', 'APPEND', ];
    public const FUNCTIONS_RETURN_ONE_ARGUMENT = ['GET', ];
    public const FUNCTIONS_PLAYBACK_ALL_NODES = ['FLUSHALL', ];

    public function rewrite($function, $argumentString) : bool
    {
        $this->buckets = [];
        $this->releventServers = [];
        $function = strtoupper($function);
        \Kint::$max_depth = 4;
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $argumentString, $arguments);
        $arguments = $arguments[0];
        \Kint::dump($argumentString, $arguments);
        array_walk($arguments, function(&$a){
            $a = trim($a, "\"");
        });

        /** @var Server[] $relevantServers */
        $relevantServers = [];
        $buckets = [];
        /** @var Status[] $responses */
        $responses = [];

        \Kint::dump($function, $arguments, $argumentString);

        if(in_array($function, self::FUNCTIONS_KEYS_FIELDS_AND_VALUES)){
            $key = array_shift($arguments);
            $hash = $this->calculateHash($key);
            $server = $this->transport->getServerByHash($hash, true);
            $relevantServers[$server->getConnection()->getRemoteAddress()] = $server;
            if(stripos($function, "SET")) {
                $data = [];
                foreach (array_chunk($arguments, 2) as $chunk) {
                    list($field, $value) = $chunk;
                    $data[$field] = $value;
                }
                $buckets[$server->getConnection()->getRemoteAddress()][$hash][$key] = $data;
            }elseif(stripos($function, "GET")){
                $buckets[$server->getConnection()->getRemoteAddress()][$hash][$key] = $arguments;
            }
            #\Kint::dump($key, $hash, $data, $buckets);
        }elseif(in_array($function, self::FUNCTIONS_KEYS_AND_VALUES)){
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

        // If we need to do this function on every node, override the buckets.
        if(in_array($function, self::FUNCTIONS_PLAYBACK_ALL_NODES)){
            foreach($this->transport->getAllWritableServers() as $i => $server){
                $buckets[$server->getConnection()->getRemoteAddress()][$i][] = null;
                $relevantServers[$server->getConnection()->getRemoteAddress()] = $server;
            }
        }

        \Kint::dump($buckets);

        // For each bucket and slot, connect to the control plane and replay the bucket at redis.
        foreach($buckets as $address => $slots){
            foreach($slots as $slot => $data) {
                $controlPlane = $relevantServers[$address]->getControlPlane();
                if(in_array($function, self::FUNCTIONS_TAKE_ONE_ARGUMENT)) {
                    foreach ($data as $k => $v) {
                        if (is_numeric($k)) {
                            #\Kint::dump($function, $v);
                            $response = $controlPlane->$function($v);
                        } else {
                            #\Kint::dump($function, $k, $v);
                            $response = $controlPlane->$function($k, $v);
                        }
                        $responses[$address][] = $response;
                    }
                }elseif(in_array($function, self::FUNCTIONS_KEYS_FIELDS_AND_VALUES)){
                    foreach($data as $key => $subData){
                        $response = $controlPlane->$function($key, $subData);
                        $responses[$address][] = $response;
                    }
                }else{
                    if (empty(array_filter($data))) {
                        \Kint::dump($function);
                        $response = $controlPlane->$function();
                    }else{
                        \Kint::dump($function, $data);
                        $response = $controlPlane->$function($data);
                    }
                    $responses[$address][] = $response;
                }
            }
        }

        #\Kint::dump($responses);

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
        \Kint::dump($responses);
        $finalResponse = call_user_func_array('array_merge', $responses);
        \Kint::dump($finalResponse);
        if(is_array(reset($finalResponse))) {
            $finalResponse = call_user_func_array('array_merge', $finalResponse);
            \Kint::dump($finalResponse);
        }
        if(count($finalResponse) == 1){
            $finalResponse = reset($finalResponse);
            \Kint::dump($finalResponse);
        }
        \Kint::dump($function, $arguments, $finalResponse);

        // Generate the RESP output
        $output = is_array($finalResponse)
            ? $this->transport->createRespArray($finalResponse)
            : "+{$finalResponse}";
        $this->transport->sendClientMessage($output);

        // Generate the debug message
        $debug = "{$function} ";
        foreach($arguments as $argument){
            $debug.= "{$argument} ";
        }
        $debug.= "=> {$output}";
        $this->transport->getLogger()->debug($debug);

        return true;

    }

}