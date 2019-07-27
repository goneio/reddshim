<?php
namespace Gone\ReddShim\Tests\Connectivity;

use Gone\ReddShim\ReddShimSourceSelectCommand;
use Gone\ReddShim\Tests\TestCommon;
use Gone\ReddShim\Tests\TestRedis;
use Predis\Client as PredisClient;
use Predis\Connection\StreamConnection;
use Predis\Response\Status;

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
                //'database' => rand(0,15),
                'timeout' => 1.0,
            ]
        );

        // Send the REDDSHIM SOURCE $HOST command
        $this->predis
            ->getConnection()
                ->addConnectCommand(
                    new ReddShimSourceSelectCommand("SOLO")
                )
        ;

        //$this->predis->connect();
        $this->predis->select(self::$redisDatabaseId);

        // Something about this startup process leads to an extra "OK" buffered.. For now, this  is a filthy hack.
        // @todo fix the underlying issue
        /** @var StreamConnection $connection */
        $connection = $this->predis->getConnection();
        $connection->read();

    }

    public function tearDown()
    {
        $this->predis->disconnect();
        parent::tearDown();
    }
}