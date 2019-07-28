<?php
namespace Gone\ReddShim\Tests;

use Gone\UUID\UUID;

abstract class TestRedisCli extends TestCommon {

    abstract protected function redisCli(string $command, $debug = false) : string;

    public function testPing()
    {
        $this->assertEquals("PONG", $this->redisCli("PING"));
    }

    public function testFlushAll(){
        $key = UUID::v4();
        $value = self::$faker->words(5, true);
        $this->assertEquals("OK", $this->redisCli("SET {$key} '{$value}'"));
        $this->assertEquals($value, $this->redisCli("GET {$key}"));
        $this->assertEquals("OK", $this->redisCli("FLUSHALL"));
        $this->assertEmpty($this->redisCli("GET {$key}"));
    }

    public function testSet(){
        $key = UUID::v4();
        $value = self::$faker->words(5, true);
        $this->assertEquals("OK", $this->redisCli("SET {$key} '{$value}'"));
        return [$key, $value];
    }

    /**
     * @depends testSet
     */
    public function testGet($n){
        list($key, $value) = $n;
        $this->assertEquals($value, $this->redisCli("GET {$key}"));
        $this->assertEquals("", $this->redisCli("GET not-a-key"));
    }

}