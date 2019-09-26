<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/25
 * Time: 14:25
 */

namespace app\power_bank\controller;

use think\Cache;
use think\Controller;
use app\power_bank\model\Insider as InsiderModel;

class Insider extends Controller
{
    # 添加内部人员的标识
    function addLists()
    {
        $weChat       = new WeChat();
        $InsiderModel = new InsiderModel();
        # 获取用户的标识  然后加入到数据中去
        $code = $this->request->param('code', NULL);
        # 根据code值获取缓存的用户标识 -- 先判断code值是否存在
        if (empty($code)) {
            $openid = '';
        } else {
            $openid = Cache::get($code, NULL);
        }
        # 判断用户标识为空
        if (empty($openid)) {
            # 调用获取用户的数据接口
            $openid = $weChat->getOpenid($code);
        }
        # 判断是否存在该用户
        $InsiderModel->data = ['where' => ['subopenid' => $openid,]];
        $res                = $InsiderModel->getOne();
        if (empty($res)) {
            # 添加数据
            $InsiderModel->data = [['subopenid' => $openid,]];
            $res                = $InsiderModel->updateMore();
            if ($res > 0) {
                die('添加成功');
            }
            die('添加失败');
        } else {
            die('已经存在，请勿重复添加');
        }

    }
}