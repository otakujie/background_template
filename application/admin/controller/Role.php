<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/23 0023
 * Time: 下午 8:50
 */

namespace app\admin\controller;


use app\common\model\Auths;
use app\common\model\Roles;
use app\common\model\Users;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

class Role extends Base
{
    function lists()
    {
        // 查询出role列表
        $roles = new Roles();
        try {
            $list = $roles->order('level')->field('role_name,role_id,level')->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        // 权限列表
        $auths = new Auths();
        try {
            $auth_list = $auths->field('auth_id,auth_name,auth_rules,auth_type')->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        $this->assign('auth_list', $auth_list ?? []);
        $this->assign('list', $list);
        return view();
    }

    function add()
    {

    }

    function edi()
    {
        $role_id = input('role_id');
        if (empty($role_id)) die('传参错误');

        // 获取用户信息
        try {
            $info = db('roles')->where(['role_id' => $role_id])->find();
            if ( empty($info) ) die('信息为空');
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        } catch (Exception $e) {
        }
        // 组成权限字符
        $roleList = explode(',', $info['role_id']);
        $roleStr = "{";
        foreach ($roleList as $rkey => $role){
            $roleStr .= "'role[$rkey]' : '1',";
        }
        $roleStr .= "}";

        try {
            $users = new Users();
            $auth_list = $users->getAuth($this->request->param('do_uid'));
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        $this->assign('auth_list', $auth_list ?? []);
        $this->assign('info', $info);
        $this->assign('roleStr', $roleStr);
        return view();
    }
}