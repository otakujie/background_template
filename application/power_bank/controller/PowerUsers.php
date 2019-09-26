<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/17
 * Time: 15:50
 */

namespace app\power_bank\controller;

use Siam\Api;
use think\Cache;
use think\Request;
use app\power_bank\model\PowerUsers as PowerUsersModel;

class PowerUsers
{
    # 获取用户的信息
    function lists()
    {
        # 获取查询的用户信息
        $code = Request::instance()->param('identification', NULL);
        # 获取缓存的用户标识
        $openid = Cache::get($code, NULL);
        # 判断数据是否存
        if (empty($openid)) {
            Api::json('300', [], '数据为空');
        }
        # 查询对应标书的用户信息
        $powerUserModel       = new PowerUsersModel();
        $powerUserModel->data = [
            'where' => [
                'subopenid' => $openid,
            ],
        ];
        $userInfo             = $powerUserModel->getOne();

        if (!empty($userInfo)) {
            Api::json('200', ['info' => ['lists' => $userInfo]], '获取成功');
        }
        Api::json('300', [], '暂无数据');
    }
}