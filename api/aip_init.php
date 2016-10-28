<?php

// 载入composer的autoload文件
include __DIR__ . '/vendor/autoload.php';

use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;

$database = [
    'driver'    => 'mysql',
    'host'      => '192.168.2.233',
    'database'  => 'ecshop',
    'username'  => 'www',
    'password'  => 'DNW3$5^7*#4%6&8',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => 'ecs_',
];

define('REDIS_HOST','192.168.2.233');
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