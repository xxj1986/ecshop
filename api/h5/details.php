<?php

/**
 * ECSHOP 首页文件
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: index.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/../../includes/init.php');
require(dirname(__FILE__) . '/../aip_init.php');

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

$mod = $_GET['mod'];
$id = intval($_GET['id']);

switch($mod){
    case 'activity':
        $data = activity($id);
        $view = '';
        break;

}

$smarty->assign('data', $data); //详细信息
$smarty->display($view);


/**
 *  活动详情H5
 */
function activity($id)
{
    $oneInfo = [];
    //获取数据
    return $oneInfo;
}
