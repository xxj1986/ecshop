<?php

// 载入composer的autoload文件
include __DIR__ . '/vendor/autoload.php';

use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;

$database = [
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'ecshop',
    'username'  => 'root',
    'password'  => 'link@w1nd0ws',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => 'ecs_',
];

define('REDIS_HOST','127.0.0.1');
define('REDIS_PORT',6379);

$db = new DB;

// 创建链接
$db->addConnection($database);
// Set the event dispatcher used by Eloquent models
$db->setEventDispatcher(new Dispatcher(new Container));

// 设置全局静态可访问
$db->setAsGlobal();

// 启动Eloquent
$db->bootEloquent();