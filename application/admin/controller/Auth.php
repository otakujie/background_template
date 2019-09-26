<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/23 0023
 * Time: 下午 8:51
 */

namespace app\admin\controller;


use app\common\model\Auths;
use app\common\model\Menu;
use think\Controller;
use think\exception\DbException;

class Auth extends Controller
{

    /**
     * 权限管理页面 管理就是管理全部 哪怕普通角色拥有某权限 也没办法管理删除该权限！
     * @return \think\response\View
     */
    function lists()
    {
        $Menu = Menu::getInstance();
        $Menu->onlyMenu = false;
        $list = $Menu->get(1);
        // halt($list);

        $html = $this->makeTree($list);

        $this->assign('list', $list);
        $this->assign('html', $html);
        return view();
    }

    protected function makeTree(array $array)
    {
        if (empty($array) || !is_array($array)) return '';
        $html = '<ol class="dd-list">';
        foreach ($array as $key => $value){
            $html .= <<<html
<li class="dd-item" data-id="{$value['auth_id']}">
    <div class="dd-handle">{$value['auth_name']} </div>
    <div class="dd-btn" style="position: absolute;right: 5px;top: 7px;">
        <a href="javascript:ediAuth('{$value['auth_id']}');">编辑</a>
    </div>
html;

            // 如果还有下级
            if (isset($value['child'])){
                $html .= $this->makeTree($value['child']);
            }
            $html .= "</li>";
        }
        $html .= "</ol>";
        return $html;
    }

    function add()
    {

    }

    function edi()
    {
        $validateRes = $this->validate(input(),[
            'auth_id' => 'require',
        ]);
        if ($validateRes !== true){
            return $validateRes;
        }

        try {
            $info = Auths::get([
                'auth_id' => input('auth_id'),
            ]);
        } catch (DbException $e) {
            return "错误 auth信息不存在";
        }

        $this->assign('info', $info);

        return $this->fetch();
    }
}