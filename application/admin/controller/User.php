<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/23 0023
 * Time: 下午 8:51
 */

namespace app\admin\controller;


use app\common\model\Auths;
use app\common\model\Roles;
use app\common\model\Users;
use think\Config;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use app\api\controller\Commom;

class User extends Base
{
    function lists()
    {
        // $model = new Users();
        // // 测试直属下级
        // halt($model->getDirectlyChild(1, ['field' => 'u_id','get_array' => 1]));
        // // 测试所有下级 无限级
        // halt($model->getAllChild(1, ['field' => 'u_id','get_array' => 1]));

        $role      = new Roles();
        $role_list = $role->select();
        $roleName  = '{';
        foreach ($role_list as $key => $value) {
            $roleName .= "'{$value['role_id']}' : '{$value['role_name']}',";
        }
        $roleName .= '}';
        $this->assign('role_name', $roleName);
        return view();
    }

    function add()
    {
        # 获取添加用户的数据
        $doUid = $this->request->param('do_uid');

        # 获取用户信息
        $usrRes = Users::get(['u_id' => $doUid]);
        # 查询数据
        $roles     = new Roles();
        $role_list = $roles->order('level')->select();
        $users     = new Users();
        $auth_list = $users->getAuth($doUid);

        $commom    = new Commom();
        $commomRes = $commom->userControl($doUid, '26', TRUE);
        # 获取用户角色的登记
        $userlevel = Roles::get(['role_id' => $usrRes['role_id']]);

        # 重新生成数据
        $roleInfo = [];
        $authInfo = [];
        # 判断用户角色是否为管理员  是否话就显示全部角色
        foreach ($role_list as $value) {
            # 5 是代理角色的role_id

            # 当登录账号是否1001的时候  显示全部的角色
            if ($usrRes['u_account'] == '1001') {
                $roleInfo[] = $value;
            } else {
                if ($commomRes) {
                    if ($value['role_id'] == '5') {
                        $roleInfo[] = $value;
                    }
                }
                if ($value['level'] > $userlevel['level'] && $value['role_id'] != '5') {
                    $roleInfo[] = $value;
                }
            }
        }


        # 获取角色所对应的权限
        if ($usrRes['u_account'] != '1001') {
            $userAuth = $usrRes['u_auth'];
            $userRole = Roles::get(['role_id' => $usrRes['role_id']]);
            $userAuth .= ','.$userRole['role_auth'];
            $userAuth = trim($userAuth, ',');
            $userAuth = explode(',', $userAuth);
            # 判断权限列表
            $authRes = [];
            foreach ($auth_list as $value) {
                $authRes[$value['auth_id']] = $value;
            }
            # 整合所有的权限
            foreach ($userAuth as $value) {
                if (isset($authRes[$value])) {
                    $authInfo[] = $authRes[$value];
                }
            }
        } else {
            $authInfo = $auth_list;
        }

        $this->assign('role_list', $roleInfo);
        $this->assign('auth_list', $authInfo);

        return view();
    }

    function edit()
    {
        $doUid = $this->request->param('do_uid');
        $u_id  = input('u_id');
        if (empty($u_id)) die('传参错误');

        // 获取用户信息
        try {
            $info = db('users')->where(['u_id' => $u_id])->find();
            if (empty($info)) die('信息为空');
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        } catch (Exception $e) {
        }
        // 组成权限字符
        $roleList = explode(',', $info['role_id']);
        $roleStr  = "{";
        foreach ($roleList as $rkey => $role) {
            $roleStr .= "'role[$role]' : '1',";
        }
        $roleStr .= "}";

        # 获取用户信息
        $usrRes = Users::get(['u_id' => $doUid]);
        # 查询数据
        $roles     = new Roles();
        $role_list = $roles->order('level')->select();
        $users     = new Users();
        $auth_list = $users->getAuth($doUid);

        $commom    = new Commom();
        $commomRes = $commom->userControl($doUid, '26', TRUE);
        # 获取用户角色的登记
        $userlevel    = Roles::get(['role_id' => $usrRes['role_id']]);
        $agencyRoleId = Config::get('agencyRoleId', NULL);

        # 重新生成数据
        $roleInfo = [];
        $authInfo = [];
        # 判断用户角色是否为管理员  是否话就显示全部角色
        foreach ($role_list as $value) {
            # 5 是代理角色的role_id

            # 当登录账号是否1001的时候  显示全部的角色
            if ($usrRes['u_account'] == '1001') {
                $roleInfo[] = $value;
            } else {
                if ($commomRes) {
                    if ($value['role_id'] == $agencyRoleId) {
                        $roleInfo[] = $value;
                    }
                }
                if ($value['level'] > $userlevel['level'] && $value['role_id'] != $agencyRoleId) {
                    $roleInfo[] = $value;
                }
            }
        }


        # 获取角色所对应的权限
        if ($usrRes['u_account'] != '1001') {
            $userAuth = $usrRes['u_auth'];
            $userRole = Roles::get(['role_id' => $usrRes['role_id']]);
            $userAuth .= ','.$userRole['role_auth'];
            $userAuth = trim($userAuth, ',');
            $userAuth = explode(',', $userAuth);
            # 判断权限列表
            $authRes = [];
            foreach ($auth_list as $value) {
                $authRes[$value['auth_id']] = $value;
            }
            # 整合所有的权限
            foreach ($userAuth as $value) {
                if (isset($authRes[$value])) {
                    $authInfo[] = $authRes[$value];
                }
            }
        } else {
            $authInfo = $auth_list;
        }

        $this->assign('role_list', $roleInfo ?? []);
        $this->assign('auth_list', $authInfo ?? []);
        $this->assign('info', $info);
        $this->assign('roleStr', $roleStr);
        return view();
    }
}