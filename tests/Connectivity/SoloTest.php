<?php
namespace Gone\ReddShim\Tests\Connectivity;

use Gone\ReddShim\ReddShimSourceSelectCommand;
use Gone\ReddShim\Tests\TestCommon;
use Predis\Client as PredisClient;
use Predis\Connection\StreamConnection;

class SoloTest extends TestCommon
{
   /** @var PredisClient */
    protected $predis;

    public function setUp()
    {
        parent::setUp();
        $this->predis = new PredisClient(
            sprintf(
                "%s://%s:%d",
                "tcp",
                self::ADDRESS,
                self::PORT
            )
        );

        // Send the REDDSHIM SOURCE $HOST command
        $this->predis
            ->getConnection()
                ->addConnectCommand(
                    new ReddShimSourceSelectCommand("SOLO")
                )
        ;
    }

    public function testPing()
    {
        \Kint::dump($this->predis->ping());
    }
}