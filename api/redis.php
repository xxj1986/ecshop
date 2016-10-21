<?php

include __DIR__ . '/sign.php';

$sign = new Sign();
$sign->checkSign();

echo 'check OK';