<?php
namespace Gone\ReddShim\Tests\RedisCli;

use AdamBrett\ShellWrapper\Runners\Runner;
use AdamBrett\ShellWrapper\Runners\ShellExec;
use Gone\ReddShim\Tests\ShellWrapper\UnescapedCommand;
use Gone\ReddShim\Tests\TestRedisCli;
use AdamBrett\ShellWrapper\Command;

class SoloTest extends TestRedisCli{

    /** @var Runner */
    protected $runner;
    /** @var Command */
    protected $redisCliCommand;

    protected static $redisCliDebugMode = true;

    public function setUp()
    {
        parent::setUp();
        $this->initRedisCli();
    }

    private function initRedisCli(){
        $this->runner = new ShellExec();
        $this->redisCliCommand = new Command("redis-cli");
        $this->redisCliCommand->addFlag(new Command\Flag("h",self::ADDRESS));
        $this->redisCliCommand->addFlag(new Command\Flag("p",self::PORT));
        $this->redisCliCommand->addFlag(new Command\Flag("a", implode(":", ['SOLO', self::USERNAME, self::PASSWORD])));
        $this->redisCliCommand->addFlag(new Command\Flag("n", self::$redisDatabaseId));
    }

    public function redisCli(string $command): string
    {
        $this->redisCliCommand->addParam(new UnescapedCommand($command));
        $output = trim($this->runner->run($this->redisCliCommand));
        if(self::$redisCliDebugMode){
            \Kint::dump($this->redisCliCommand->__toString(), $output);
        }
        $this->initRedisCli();
        return $output;
    }
}