<?php

// 载入初始化文件
include __DIR__ . '/aip_init.php';

use Illuminate\Database\Eloquent\Model  as Eloquent;

class AdminUser extends  Eloquent{ protected $table = 'admin_user';}

$adminModel = new AdminUser();

$user = $adminModel->first();

echo ($user->user_name);


class Auth{
    protected $dev;
    public function __construct()
    {
        $this->dev = isset($_REQUEST['device'])?trim($_REQUEST['device']):'00000000';
    }

    public function getDev(){
        return $this->dev;
    }
}

$auth = new Auth();

echo $auth->getDev();






