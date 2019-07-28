<?php
namespace Gone\ReddShim\Tests\Connectivity;

use Gone\ReddShim\Tests\TestCommon;

abstract class EchoTest extends TestCommon {

    public function setUp()
    {
        parent::setUp();
    }

    public function testRoundTrip(){
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->assertIsResource($socket);

        $this->assertTrue(socket_connect($socket, self::ADDRESS, self::PORT));

        $testData = self::$faker->words(100, true);

        $this->assertEquals(strlen($testData), socket_write($socket, $testData, strlen($testData)));

        $buf = socket_read($socket, "\n",  PHP_NORMAL_READ);

        $this->assertEquals($testData, $buf);

        socket_close($socket);
    }
}