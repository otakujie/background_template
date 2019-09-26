<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/7/17
 * Time: 10:13
 */

namespace app\api\controller;

use Siam\Api;
use think\Config;

class SearchBoxContent extends Base
{
    /**
     * 获取全局配置好页面选项框显示的的数据
     */
    public function lists()
    {
        $result = Config::get('SearchBoxContent', NULL);

        if (empty($result)) {
            Api::json('300', [], '配置数据不存在');
        }
        Api::json('200', ['lists' => $result], '数据获取成功');
    }
}