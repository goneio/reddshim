<?php
namespace Gone\ReddShim\Tests;

use Predis\Client as PredisClient;
use Predis\Response\Status;

abstract class TestRedis extends TestCommon {

    /** @var PredisClient */
    protected $predis;

    /**
     * @group util
     */
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

    /**
     * @group get
     */
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
     * @group get
     * @depends testSet
     */
    public function testGet($data)
    {
        list($key, $expected) = $data;

        $value = $this->predis->get($key);

        $this->assertEquals($expected, $value);
    }

    /**
     * @group mget
     */
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
     * @group mget
     * @depends testMSet
     */
    public function testMGet($data)
    {
        $result = $this->predis->mget(array_keys($data));
        $this->assertEquals(array_values($data), $result);
    }

    /**
     * @group hmget
     */
    public function testHMSet()
    {
        $data = [
            "field-" . rand(1000,9999) => self::$faker->words(3, true),
            "field-" . rand(1000,9999) => self::$faker->words(3, true),
        ];

        $key = "key:" . rand(1000,9999);

        $hSetResponse = $this->predis->hmset($key, $data);

        $this->assertInstanceOf(Status::class, $hSetResponse);
        $this->assertEquals("OK", $hSetResponse->getPayload());
        return [$key, $data];
    }

    /**
     * @group hmget
     * @depends testHMSet
     */
    public function testHMGet($pass){
        list($key, $data) = $pass;
        $hMGetResponse = $this->predis->hmget($key, array_keys($data));
        $this->assertEquals(array_values($data), $hMGetResponse);
    }

    /**
     * @group get
     * @depends testSet
     */
    public function testAppend($n){
        list($key, $value) = $n;
        $append = self::$faker->word;
        /** @var Status $response */
        $response = $this->predis->append($key, $append);
        $this->assertInstanceOf(Status::class, $response);
        $this->assertEquals(strlen($value . $append), $response->getPayload());
        $this->assertEquals($value . $append, $this->predis->get($key));
    }
}