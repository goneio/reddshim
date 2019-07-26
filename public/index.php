<?php

require_once(__DIR__ . "/../bootstrap.php");
$core = APP_CORE_NAME;
$app = new $core();
$app
    ->loadAllRoutes()
    ->getApp()
    ->run();
