<?php
namespace Gone\ReddShim\Tests;

use Predis\Client as PredisClient;
use Predis\Response\Status;

abstract class TestRedis extends TestCommon {

    /** @var PredisClient */
    protected $predis;

    public function testPing()
    {
        /** @var Status $pingResponse */
        $pingResponse =$this->predis->ping();
        $this->assertInstanceOf(Status::class, $pingResponse);
        $this->assertEquals("PONG", $pingResponse->getPayload());

        $message = self::$faker->words(5, true);
        $pingResponse =$this->predis->ping($message);
        $this->assertEquals($message, $pingResponse);
    }

    public function testSet()
    {
        $key = $this->generateKey();
        $value = self::$faker->words(5, true);
        $setResponse = $this->predis->set($key, $value);

        $this->assertInstanceOf(Status::class, $setResponse);
        $this->assertEquals("OK", $setResponse->getPayload());
        return [$key, $value];
    }

    /**
     * @depends testSet
     */
    public function testGet($data)
    {
        list($key, $value) = $data;

        $this->assertEquals($value, $this->predis->get($key));
    }

    public function testMSet(){
        $data = [
            $this->generateKey() => self::$faker->words(5, true),
            $this->generateKey() => self::$faker->words(5, true),
        ];

        $mSetResponse = $this->predis->mset($data);

        $this->assertInstanceOf(Status::class, $mSetResponse);
        $this->assertEquals("OK", $mSetResponse->getPayload());
        return $data;
    }

    /**
     * @depends testMSet
     */
    public function testMGet($data)
    {
        $result = $this->predis->mget(array_keys($data));
        $this->assertEquals(array_values($data), $result);
    }

    public function testHMSet()
    {
        $data = [
            $this->generateKey() => self::$faker->words(5, true),
            $this->generateKey() => self::$faker->words(5, true),
        ];

        $key = $this->generateKey();

        $hSetResponse = $this->predis->hmset($key, $data);

        $this->assertInstanceOf(Status::class, $hSetResponse);
        $this->assertEquals("OK", $hSetResponse->getPayload());
        return [$key, $data];
    }

    /**
     * @depends testHMSet
     */
    public function testHMGet($pass){
        list($key, $data) = $pass;
        $hMGetResponse = $this->predis->hmget($key, array_keys($data));
        $this->assertEquals(array_values($data), $hMGetResponse);
    }

    protected function generateKey() : string
    {
        $words = self::$faker->words(3);
        return implode(":", $words);
    }
}