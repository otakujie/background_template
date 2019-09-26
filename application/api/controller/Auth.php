<?php
/**
 * 权限api类
 * User: Siam
 * Date: 2019/4/23
 * Time: 13:53
 */

namespace app\api\controller;


use app\common\model\Auths;
use app\common\model\Menu;
use app\common\model\System;
use Siam\Api;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Validate;

class Auth extends Base
{
    /**
     * 获取菜单列表
     */
    function get_menu()
    {
        $Menu = Menu::getInstance();
        $Menu->onlyMenu = true;
        $list = $Menu->get($this->token['u_id']);
        // 组成html
        $html = '';
        foreach ($list as $key => $value) {
            if (!empty($value['child'])) {

                $html .= <<<html
<li class="layui-nav-item">
    <a href="javascript:;" lay-tips="{$value['auth_name']}"  lay-direction="2">
    <i class="layui-icon {$value['auth_icon']}"></i>
        <cite>{$value['auth_name']}</cite>
    </a>
    <dl class="layui-nav-child">
html;

                foreach ($value['child'] as $v) {
                    if (!empty($v['child'])) {
                        // 三级
                        $html .= <<<html
<dd>
    <a href="javascript:;">{$v['auth_name']}</a>
    <dl class="layui-nav-child">
html;
                        foreach ($v['child'] as $threev) {
                            $temUrl = url($threev['auth_rules']);
                            $html   .= <<<html
<dd><a lay-href="{$temUrl}">{$threev['auth_name']}</a></dd>
html;
                        }
                        $html .= "</dl>";
                        // 三级结束
                    } else {
                        $temUrl = url($v['auth_rules']);
                        $html   .= <<<html
<dd>
    <a lay-href="{$temUrl}">
    {$v['auth_name']}
    </a>
</dd>
html;
                    }
                }

                $html .= "</dl></li>";
                // 二级结束

            } else {
                // 一级的
                $temUrl = url("{$value['auth_rules']}");

                $html .= <<<html
<li data-name="{$value['auth_name']}" class="layui-nav-item">
    <a lay-href="{$temUrl}" lay-tips="{$value['auth_name']}" lay-direction="2">
        <i class="layui-icon {$value['auth_icon']}"></i>
        <cite>{$value['auth_name']}</cite>
    </a>
</li>
html;
            }
        }


        Api::json('200', ['info' => ['html' => $html]]);
    }

    /**
     * 更新菜单排序
     */
    function order_update()
    {
        $order = $this->request->param('order');

        if (empty($order)) Api::json('400', [], 'PARAMETERS_INVALID');

        // 字符替换
        $order = str_replace('children', 'child', $order);

        $system = new System();
        $res    = $system->isUpdate(TRUE)->save([
            'auth_order' => $order,
        ], ['id' => 1]);
        if ($res) {
            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function add()
    {
        $model = new Auths();
        // 分割权限 设置符合的规则
        list($moudle, $controller, $action) = explode('/', $this->request->param('auth_rules'));
        // 模块小写 和 控制器名首字母大写
        $moudle     = strtolower($moudle);
        $controller = ucfirst(strtolower($controller));

        $res = $model->save([
            'auth_name'  => $this->request->param('auth_name'),
            'auth_rules' => $moudle."/".$controller."/".$action,
            'auth_type'  => $this->request->param('auth_type'),
        ]);

        if ($res) {
            // 如果是菜单还要更新排序
            $system      = new System();
            $systeminfo  = $system->get(['id' => 1]);
            $authOrder   = json_decode($systeminfo['auth_order'], TRUE);
            $authOrder[] = [
                'id' => $model->auth_id,
            ];
            $system->isUpdate(TRUE)->save([
                'auth_order' => json_encode($authOrder),
            ], ['id' => 1]);

            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function getlist()
    {
        $model = new Auths();
        try {
            $lists = $model->field('auth_id,auth_name,auth_rules,auth_type')->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (!empty($lists)) {
            Api::json('200', ['auth' => ['lists' => $lists]], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    public function edi()
    {
        $validate = new Validate();
        $res = $validate->check(input(), [
            'auth_id' => 'require'
        ]);
        if ($res !== true){
            Api::json('500', ['error'=> ['msg'=>$validate->getError()]], "PARAMETERS_INVALID");
        }

        try {
            $res = Auths::get(['auth_id' => input('auth_id')])->allowField(TRUE)->save(input());
        } catch (DbException $e) {
            $res = false;
        }

        Api::json('200', [], 'SUCCESS');
    }
}