<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 10:17
 */

namespace app\api\controller;


use app\common\model\Users;
use Siam\Api;
use think\Cache;
use think\Request;
use think\Validate;

class Base
{
    protected $request;
    protected $token;

    /**
     * 基础方法，不需要token验证，填写格式：控制器 首字母大写/方法名
     * @var array
     */
    private $baseMethod = [
        '/User/login',
        '/Auth/get_menu',
        '/User/foreign_login',
        '/Order/create_order',
        '/Order/success_order',
        '/CarTicket/check',
        '/CarTicket/inquire_tickets',
        '/CarTicket/get_package',
        '/CarSignIn/car_sign',
        '/Line/external_lines',
        '/Station/external_station',
    ];

    function __construct()
    {
        $request       = \request();
        $this->request = $request;

        // 验证token
        if (!in_array("/{$request->controller()}/{$request->action()}", $this->baseMethod)) {
            if (input('?access_token')) {
                $this->token = \Siam\JWT::getInstance()->setSecretKey(config('app.jwt_secretkey'))->decode(input('access_token'));
                if ($this->token !== NULL && !is_array($this->token)) {
                    Api::json('500', ['error' => ['des' => $this->token]], 'TOKEN_INVALID');
                }
            } else {
                Api::json('400', ['error' => ['fields' => 'access_token']], 'PARAMETERS_INVALID');
            }
            // token验证通过 还要验证接口权限
            // $authList = $this->_getAuthList($this->token['u_id'] ?? 0);
            // $authRes  = $this->vif_auth("{$request->module()}/{$request->controller()}/{$request->action()}", $authList);
            // if ($authRes === false){
            //     Api::json('500', [], 'AUTH_NOTEXIST');
            // }
        }

        if (input('?access_token')) {
            $this->token = \Siam\JWT::getInstance()->setSecretKey(config('app.jwt_secretkey'))->decode(input('access_token'));
        }

    }

    /**
     * 获取权限列表
     * @param $uId
     * @return array
     */
    private function _getAuthList($uId)
    {
        $authList = (new Users())->getAuth($uId);
        return $authList ?? [];
    }

    private function vif_auth(string $string, array $authList)
    {
        if (empty($authList)) return FALSE;
        // 先判断缓存是否有结果
        if (Cache::get($this->token['u_id']."_".$authList[0]->auth_id)) return TRUE;

        foreach ($authList as $key => $auth) {
            if ($auth->auth_rules == $string) return TRUE;

            // 分割权限 设置符合的规则
            list($moudle, $controller, $action) = explode('/', $string);
            // 模块和控制器名首字母大写
            $moudle     = strtolower($moudle);
            $controller = ucfirst(strtolower($controller));

            // 分割权限 设置符合的规则
            list($auth_moudle, $auth_controller, $auth_action) = explode('/', $auth->auth_rules);
            // 模块和控制器名首字母大写
            $auth_moudle     = strtolower($auth_moudle);
            $auth_controller = ucfirst(strtolower($auth_controller));

            if ($moudle == $auth_moudle || $auth_moudle == '*') {
                if ($controller == $auth_controller || $auth_moudle == '*') {
                    if ($action == $auth_action || $auth_action == '*') {
                        // 做一下缓存  用户id_权限id
                        Cache::set($this->token['u_id']."_".$auth->auth_id, TRUE, 1800); // 30分钟有效
                        return TRUE;
                    }
                }
            }
            // 循环结束

        }
        return FALSE;
    }

    /**
     * 验证信息是否为空
     * @param $checkLists array 验证的数据
     * @param $rule array 验证规则
     */
    public function verificationInformation($checkLists = [], $rule = [])
    {
        # 判断验证数据和验证规则是否为空
        if (empty($checkLists) || empty($rule)) {
            Api::json('300', [], '规则或者数据不能为空');
        }

        $validate    = new Validate($rule);
        $checkResult = $validate->check($checkLists);

        # 发送验证错误信息
        if (!$checkResult) {
            Api::json('300', [], $validate->getError());
        }
    }
}