<?php
require_once __DIR__.'/vendor/autoload.php';

$host = '127.0.0.1';
$port = 6379;
$password = '';
$redis = \WuTi\Library\Factory\CacheFactory::redisInstance($host,$port,$password);
$info = $redis->info();
var_dump($info);


