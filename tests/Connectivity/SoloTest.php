<?php
namespace Gone\ReddShim\Tests\Connectivity;

use Gone\ReddShim\Tests\TestRedis;
use Predis\Client as PredisClient;

class SoloTest extends TestRedis
{
   /** @var PredisClient */
    protected $predis;

    /** @var int */
    private static $redisDatabaseId = 0;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$redisDatabaseId = rand(0,15);
    }

    public function setUp()
    {
        parent::setUp();
        $this->predis = new PredisClient(
            [
                'scheme' => 'tcp',
                'host' => self::ADDRESS,
                'port' => self::PORT,
                'database' => self::$redisDatabaseId,
                'timeout' => 1.0,
                'password' => 'SOLO:'.self::USERNAME.':'.self::PASSWORD,
            ]
        );
    }

    public function tearDown()
    {
        $this->predis->disconnect();
        parent::tearDown();
    }
}