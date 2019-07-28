<?php
namespace Gone\ReddShim\Tests;

use Gone\UUID\UUID;

abstract class TestRedisCli extends TestCommon {

    abstract protected function redisCli(string $command) : string;

    public function testPing()
    {
        $this->assertEquals("PONG", $this->redisCli("PING"));
    }

    public function testMSet()
    {
        $this->assertEquals("OK", $this->redisCli("MSET key:001 \"these\" key:002 \"are\" key:003 \"words\""));
    }

    public function testMGet()
    {
        $this->assertEquals("these\nare\nwords", $this->redisCli("MGET key:001 key:002 key:003"));
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

    /**
     * @depends testSet
     */
    public function testAppend($n)
    {
        list($key, $value) = $n;
        $append = self::$faker->word;
        $this->assertEquals(strlen($value. $append), $this->redisCli("APPEND {$key} \"{$append}\""));
        $this->assertEquals($value . $append, $this->redisCli("GET {$key}"));
    }



}