<?php
namespace Gone\ReddShim\Tests\Connectivity;

use Gone\ReddShim\Tests\TestRedis;
use Predis\Client as PredisClient;

class ClusterTest extends TestRedis
{
   /** @var PredisClient */
    protected $predis;

    public function setUp()
    {
        parent::setUp();
        $this->predis = new PredisClient(
            [
                'scheme' => 'tcp',
                'host' => self::ADDRESS,
                'port' => self::PORT,
                // Redis cluster mode doesn't support database numbers
                //'database' => self::$redisDatabaseId,
                'timeout' => 1.0,
                'password' => implode(":", [
                    'CLUSTER',
                    self::USERNAME,
                    self::PASSWORD
                ]),
            ]
        );
    }

    public function tearDown()
    {
        $this->predis->disconnect();
        parent::tearDown();
    }
}