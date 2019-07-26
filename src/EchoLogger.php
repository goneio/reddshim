<?php
namespace Gone\ReddShim;

class EchoLogger {
    public function __call($name, $arguments)
    {
        echo "{$name} {$arguments[0]}\n";
    }
}