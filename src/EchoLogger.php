<?php
namespace Gone\ReddShim;

use Psr\Log\LoggerInterface;

class EchoLogger
    implements LoggerInterface
{
    public function __call($name, $arguments)
    {
        echo "{$name} {$arguments[0]}\n";
    }

    public function emergency($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function alert($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function critical($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function error($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function warning($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function notice($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function info($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function debug($message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }

    public function log($level, $message, array $context = array())
    {
        $this->__call(__FUNCTION__, [$message]);
    }
}