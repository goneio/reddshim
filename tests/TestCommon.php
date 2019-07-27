<?php
namespace Gone\ReddShim\Tests;

use Gone\Testing\TestCase;

class TestCommon extends TestCase{
    protected const ADDRESS="reddshim";
    protected const PORT=6379;
    protected const USERNAME="TestUser";
    protected const PASSWORD="ChangeMe";

    /** @var int */
    protected static $redisDatabaseId = 0;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$redisDatabaseId = rand(0,15);
    }
}