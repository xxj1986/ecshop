<?php

// 载入composer的autoload文件
include __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$database = [
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'ecshop',
    'username'  => 'root',
    'password'  => 'link@w1nd0ws',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => 'ecs_',
];

$capsule = new Capsule;

// 创建链接
$capsule->addConnection($database);

// 设置全局静态可访问
$capsule->setAsGlobal();

// 启动Eloquent
$capsule->bootEloquent();