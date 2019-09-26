<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 10:15
 */

namespace app\api\controller;


use app\common\model\Roles;
use app\common\model\Stations;
use app\common\model\Users;
use Siam\Api;
use think\Config;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Exception;
use think\exception\DbException;
use think\Request;

class User extends Base
{
    static $code = [
        '500' => '500', // 通用失败
        '200' => '200', // 通用成功
    ];
    // ------------------ 接口注释
    // login - USER_NOT_EXIST 用户不存在，USER_PASSWORD_ERROR 密码错误，USER_NOT_TO_USED 用户被禁用，LOGIN_SUCCESS 登陆成功
    //
    //
    // ------------------ 注释结束

    function login()
    {
        $user_name = $this->request->param('useraccount');
        $password  = $this->request->param('password');

        $model = new Users();
        $has   = FALSE;

        try {
            $has = $model->where('u_account', $user_name)
                ->where('u_status', 'neq', '-1')
                ->field('u_account,u_name,u_password,u_id,p_u_id,role_id,u_status')
                ->find();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (!$has) Api::json(self::$code['500'], [], 'USER_NOT_EXIST');

        if ($has['u_password'] !== md5($password)) Api::json(self::$code['500'], [], 'USER_PASSWORD_ERROR');

        if ($has['u_status'] == '0') Api::json(self::$code['500'], [], 'USER_NOT_TO_USED');

        // 返回token
        $jwt = \Siam\JWT::getInstance();

        try {
            $jwtData = $has->toArray();
        } catch (Exception $e) {
        }

        $jwtToken = $jwt->setIss(config('app.iss'))->setSecretKey(config('app.jwt_secretkey'))
            ->setSub(config('app.iss'))->setWith($jwtData)->make();

        Api::json(self::$code['200'], ['jwtData' => ['token' => $jwtToken]], 'LOGIN_SUCCESS');
    }

    function get_list()
    {
        $roleRank  = Config::get('roleRank', NULL);
        $roleModel = new Roles();
        # 获取登录角色的信息
        $roleInfo = $roleModel->all(['role_id' => ['in', $this->token['role_id']]]);
        # 判断能够查询的用户数据等级 -- 1为查询所有的用户   -- 2为查询比自己等级的用户权限  -- 3为查询查询自己创建的用户  -- 4为只能查看自己的角色
        $userClass = 4;

        # 判断全局配置的是是否为空
        if (empty($roleRank)) {
            Api::json('300', [], '配置数据为空');
        }

        foreach ($roleInfo as $key => $value) {
            # 管理员的查询等级
            if ($roleRank['Administrator'] == $value['level']) {
                $userClass = 1;
                break;
            } else if ($roleRank['director'] >= $value['level'] && $value['level'] != $roleRank['Administrator']) {// 主任以上  管理员以下
                $userClass = 2;
                break;
            } else if ($value['level'] == $roleRank['stationAgent']) {
                $userClass = 3;
                break;
            }
        }

        $userModel = new Users();
        # 获取当前用户的角色等级
        $where['u_status'] = ['NEQ', -1];
        # 按照可查询的等级
        switch ($userClass) {
            case 2:
                # 判断用户拥有的最高权限是否经理还是主任
                foreach ($roleInfo as $key => $value) {
                    # 判断是否经理的
                    if ($value['level'] == $roleRank['manager']) {
                        # 查询角色权限比经理低的全部角色
                        $roleRes = $roleModel->all(['level' => ['GT', $value['level']]]);
                    } else if ($value['level'] == $roleRank['director']) {
                        # 查询角色权限等级比主任低的全部橘色
                        $roleRes = $roleModel->all(['level' => ['GT', $value['level']]]);
                    }
                }
                # 循环获取这些角色的id
                $roleId = '';
                foreach ($roleRes as $key => $value) {
                    $roleId .= $value['role_id'].',';
                }
                # 查询的条件
                $where['role_id'] = ['in', trim($roleId, ',')];
                break;
            case 3:
                $where['p_u_id'] = $this->token['u_id'];
                break;
            case 4:
                $where['u_id'] = $this->token['u_id'];
                break;
        };
        # 按照条件查询数据表获取用户信息
        $list      = $userModel->where($where)->page($this->request->param('page', 1))->limit($this->request->param('limit', 10))->select();
        $userCount = $userModel->where($where)->count();

        # 判断用户属于什么权限 -- 显示什么按钮
        foreach ($list as $value) {
            # 判断现在的用户是否为开发使用的用户
            if ($this->token['u_account'] === '1001') {
                # 是的就显示全部按钮
                $value['all_display'] = '4';
            } else if ($this->token['u_account'] === '100606') {
                # 除了特殊的财务总监可以充值
                $value['all_display'] = '2';
            } else {
                # 其他账号不能充值
                $value['all_display'] = '1';
            }
        }
        Api::json('200', ['info' => ['list' => $list, 'count' => $userCount]], 'SUCCESS');
    }

    function add()
    {
        $model = new Users();
        $res   = $model->addUser([
            'p_u_id'        => $this->token['u_id'],
            'u_name'        => $this->request->param('u_name'),
            'u_agency'      => $this->request->param('sales_status'),
            'u_station'     => $this->request->param('statons'),
            'u_key'         => strtoupper(md5(time().md5(time().rand(5, 100)))),
            'u_password'    => md5($this->request->param('u_password')),
            'role_id'       => implode($this->request->param('role/a', []), ","),
            'u_auth'        => $this->request->param('u_auth'),
            'notify_status' => $this->request->param('notify_status'),
            'notify_url'    => $this->request->param('notify_url'),
        ]);

        if ($res) {
            Api::json('200', ['userinfo' => ['account' => $model->u_account, 'name' => $model->u_name]], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function edi()
    {
        $model = new Users();

        $res = $model->isUpdate(TRUE)->save([
            'u_name'        => $this->request->param('u_name'),
            'role_id'       => implode($this->request->param('role/a', []), ","),
            'u_agency'      => $this->request->param('sales_status'),
            'u_station'     => $this->request->param('statons'),
            'u_auth'        => $this->request->param('u_auth'),
            'notify_status' => $this->request->param('notify_status'),
            'notify_url'    => $this->request->param('notify_url'),
        ], ['u_id' => $this->request->param('u_id')]);

        if ($res) {
            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function del()
    {
        $u_id = $this->request->param('u_id');
        if (empty($u_id)) Api::json('400', ['error' => ['fields' => 'u_id']], 'PARAMETERS_INVALID');
        // 这里可以从token的操作用户验证是否有权限操作u_id

        $model = new Users();
        $res   = $model->isUpdate(TRUE)->save([
            'u_status' => '-1',
        ], ['u_id' => $u_id]);

        if ($res) {
            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function edi_pwd()
    {
        $password    = $this->request->param('password');
        $newPassword = $this->request->param('new_password');

        $has = FALSE;

        try {
            /** @var mixed $has */
            $has = (new Users())->where('u_id', $this->request->param('u_id'))
                ->where('u_status', 'neq', '-1')
                ->field('u_account,u_name,u_password,u_id,p_u_id,role_id,u_status')
                ->find();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (!$has) Api::json(self::$code['500'], [], 'USER_NOT_EXIST');

        if ($has['u_password'] !== md5($password)) Api::json(self::$code['500'], [], 'USER_PASSWORD_ERROR');

        if ($has['u_status'] == '0') Api::json(self::$code['500'], [], 'USER_NOT_TO_USED');

        $res = Users::get($has->u_id)->save([
            'u_password' => md5($newPassword),
        ]);

        if ($res) {
            Api::json(self::$code['200'], [], 'SUCCESS');
        }
        Api::json(self::$code['500'], [], 'FAIL');

    }

    /**
     * 退票后代理的用就退钱回去代理的账号中
     * @param string $useAccount 购票订单中的用户id
     * @param array $prices 扣款的金额 用户id对应金额
     */
    function addMoney($useAccount = '', $prices = [])
    {
        # 获取要退款的用户信息
        $userRes = Users::all(['u_id' => ['in', $useAccount]]);

        # 用户余额修改后的数据
        $updataUserPrices = [];
        foreach ($userRes as $key => $value) {
            # 判断用户id和对应的金额相匹配就进行金额相加
            if ($prices[$value['u_id']]) {
                $updataUserPrices[] = [
                    'u_id'      => $value['u_id'],
                    'u_balance' => $value['u_balance'] + $prices[$value['u_id']],
                ];
            }
        }
        # 修改金额
        $usersModel = new Users();
        $res        = $usersModel->saveAll($updataUserPrices);

        # 金额修改成功后
        if (empty($res)) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * 代理用户购票扣款接口
     * @param string $useAccount 出票的代理账号
     * @param array $prices 金额
     */
    function deductMoney($useAccount = '', $prices = '')
    {
        $usersModel = new Users();
        $res        = $usersModel->where(['u_id' => $useAccount])->setDec('u_balance', $prices);

        if ($res > 0) {
            return TRUE;
        } else {
            return FALSE;
        }

    }

    /**
     * 代理用户的充值
     */
    function recharge()
    {
        $request = Request::instance();
        # 获取充值的用户id和获取充值的金额
        $uId    = $request->param('u_id', NULL);
        $prices = $request->param('prices', 0);

        # 判断数据是否为空
        if (empty($uId)) {
            Api::json('300', [], '金额不能为空');
        }
        if (empty($prices)) {
            Api::json('300', [], '充值金额不能为空或者为0');
        }

        # 充值自增
        $usersModel = new Users();
        $res        = $usersModel->where(['u_id' => $uId])->setInc('u_balance', $prices);

        if ($res > 0) {
            Api::json('200', [], '充值成功');
        }
        Api::json('300', [], '充值失败');
    }

    // 获取用户的类型 或者获取用户的余额
    function balance()
    {
        $request = Request::instance();
        # 获取数据
        $balance = $request->param('balance', FALSE);

        # 获取用户的类型
        $retult = [];
        if (!$balance) {
            $userRes            = Users::get(['u_id' => $this->token['u_id']]);
            $result['u_agency'] = $userRes['u_agency'];
        } else {
            $userRes             = Users::get(['u_id' => $this->token['u_id']]);
            $result['u_balance'] = $userRes['u_balance'];
        }
        Api::json('200', ['list' => $result], '查询成功');
    }

    /**
     * 获取该用户的无限极数据
     */
    function getAllUsers()
    {
        # 获取无限级角色
        $commom  = new Commom();
        $usersid = $commom->userControl($this->token['u_id']);

        # 获取下级所有用户
        $usersRes = Users::all(['u_id' => ['in', $usersid]]);

        $usersInfo = [];
        foreach ($usersRes as $value) {
            # 限制财务和检票员的出现
            if ($value['u_id'] != $this->token['u_id']) {
                $usersInfo[] = [
                    'u_id'   => $value['u_id'],
                    'u_name' => $value['u_name'],
                ];
            }
        }
        Api::json('200', ['info' => ['list' => $usersInfo]], '查询成功');
    }

    /**
     * 重置用户的key值
     */
    function resetKey()
    {
        $request = Request::instance();
        # 获取修改key值的用户id
        $uId = $request->param('uId', NULL);
        if (empty($uId)) {
            Api::json('300', [], '用户id不能为空');
        }

        $commom = new Commom();
        $key    = $commom->getRandomStr(32);

        $key = strtoupper(md5($key));

        $usersModel = new Users();
        $res        = $usersModel->save(['u_key' => $key], ['u_id' => $uId]);

        if ($res > 0) {
            Api::json('200', [], '重置成功');
        }
        Api::json('300', [], '重置失败');
    }

    # 判断当前用户的权限和获取当前用户的所属站点
    function get_station()
    {
        $roleModel = new Roles();
        $userModel = new Users();
        $roleRank  = Config::get('roleRank', NULL);

        # 判断全局配置的是是否为空
        if (empty($roleRank)) {
            Api::json('300', [], '配置数据为空');
        }

        # 根据用户的角色查询权限等级
        $roleRes = $roleModel->all(['role_id' => ['in', $this->token['role_id']]]);
        # 循环获取角色等级最高的
        $roleLevel = 0;
        foreach ($roleRes as $key => $value) {
            # 权限大于或等于经理的才可以选择代理
            if ($value['level'] <= $roleRank['manager']) {
                $roleLevel = 1;
                break;
            }
        }
        # 查询用户的完整信息
        $userInfo = $userModel->get(['u_id' => $this->token['u_id']]);
        # 发送
        Api::json('200', ['info' => ['showAgency' => $roleLevel, 'station' => $userInfo['u_station']]], '获取成功');
    }
}