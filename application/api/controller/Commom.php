<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/7/16
 * Time: 17:35
 */

namespace app\api\controller;


use app\common\controller\AuthTool;
use app\common\model\Roles;
use app\common\model\Tickets;
use app\common\model\Users;
use Siam\Api;
use Siam\Curl;
use think\Config;
use think\Console;

class Commom extends Base
{
    /**
     * 签名
     * @param array $data 需要签名的数据
     * @param string $key key值
     * @return string
     */
    public function encryptionSign($data = [], $key = '')
    {
        # 键值升序排序
        ksort($data);
        # 拼接数组中的键和值
        $str = '';
        foreach ($data as $k => $v) {
            $str .= "{$k}={$v}&";
        }
        # 拼接上key值
        $str .= "key={$key}";
        # md5加密  并且全部转成大写
        $str = strtoupper(MD5($str));
        # 返回签名
        $data['sign'] = $str;
        return $data;
    }


    /**
     * 验证签名
     * @param array $data 接受到的全部数据
     * @param string $key key值
     * @param string $sign 签名
     * @return bool
     */
    public function verifySign($data = [], $key = '', $sign = '')
    {
        if (!isset($data['sign'])) {
            Api::json('400', [], '签名不存在,请发送签名');
        }

        # 获取签名并且删除签名数据
        if (empty($sign)) {
            $sign = $data['sign'];
            unset($data['sign']);
        }
        # 键值升序排序
        ksort($data);
        # 拼接数组中的键和值
        $str = '';
        foreach ($data as $k => $v) {
            $str .= "{$k}={$v}&";
        }
        # 拼接上key值
        $str .= "key={$key}";
        # md5加密  并且全部转成大写
        $str = strtoupper(MD5($str));
        # 进行签名对比
        if ($sign === $str) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * 获取随机字符串
     * @param $len int 返回的随机数长度
     * @param bool $special 是否使用特殊字符
     * @return string
     */
    function getRandomStr($len, $special = TRUE)
    {
        $chars = array (
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9",
        );

        if ($special) {
            $chars = array_merge($chars, array (
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", ".",
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        return $str;
    }

    /**
     * 检测车票和订单的查询权限和可以查询的用户数据
     */
    public function userControl($uId = '', $control = '', $api = FALSE)
    {
        # 设置权限允许的情况
        $allow = FALSE;
        # 查询用户的权限选择
        if (empty($uId)) {
            $uId = $this->token['u_id'];
        }
        # 判断没有权限对应的id是  就不进行权限比对
        if (!empty($control)) {
            # 查询获取该角色的权限是否可以用
            $userRes = Users::get(['u_id' => $uId]);
            # 先判断额外添加的权限是否
            if (!empty($userRes['u_auth'])) {
                $userRes['u_auth'] = explode(',', $userRes['u_auth']);
                # 循环判断权限
                foreach ($userRes['u_auth'] as $value) {
                    if ($value == $control) {
                        $allow = TRUE;
                        break;
                    }
                }
            }

            # 额外添加的权限没有相匹配的权限  -- 就检测角色的权限
            if (!$allow) {
                $roleRes = Roles::all(['role_id' => ['in', $userRes['role_id']]]);
                # 获取所有角色的权限
                $roleLists = '';
                foreach ($roleRes as $value) {
                    $roleLists .= $value['role_auth'].',';
                }
                $roleLists = trim($roleLists, ',');
                # 把字符串权限截取成一位数组 -- 然后进行匹配
                $roleLists = explode(',', $roleLists);
                # 循环进行权限的匹配
                foreach ($roleLists as $value) {
                    if ($value == $control) {
                        $allow = TRUE;
                        break;
                    }
                }
            }
        } else {
            $allow = TRUE;
        }

        # 判断是否为接口调用
        if ($api) {
            return $allow;
        }

        # 判断用户的权限 拥有权限就查询下级用户信息
        if ($allow) {
            # 拥有使用权限的使用查询 改用户下级的用户
            $userModel  = new Users;
            $allUserRes = $userModel->getAllChild($uId, []);
            # 获取所有的可以查询的权限数据id
            $userId = '';
            foreach ($allUserRes as $value) {
                $userId .= $value['u_id'].',';
            }
            # 加上自身的角色id
            $userId .= $uId;

            return $userId;

        } else {
            return FALSE;
        }
    }


    /**
     * 检票成功后  代理用户下单的就通过这里发送到代理那里进行通知
     * @param array $ticketUser 下单用户的信息
     * @param array $checkTicket 检票的信息
     * @param string $tSn 车票号
     */
    function notified($ticketUser = [], $checkTicket = [], $tSn = '')
    {
        $url      = '';
        $signInfo = [];
        # 判断代理用户是否设置的配置的网址
        if ($ticketUser['notify_status'] === 1) {
            # 获取发送的网址
            $url = $ticketUser['notify_url'];
            # 判断获取数据
            $signData         = isset($checkTicket['second']) ? $checkTicket['second'] : $checkTicket['first'];
            $signData['t_sn'] = $tSn;
            unset($signData['stationId']);
            # 整理数据  获取签名
            $signInfo = $this->encryptionSign($signData, $ticketUser['u_key']);

        } else {
            # 获取车票信息
            $ticketRes = Tickets::get(['t_sn' => $tSn]);
            # 通过转换车票的时候写入发哦车票中用户标识那里的用户id
            $number = is_numeric($ticketRes['o_buyer_iden']) ? TRUE : FALSE;
            # 判断为true的时候
            if ($number) {
                if ($ticketRes['o_buyer_iden'] != $ticketRes['u_id']) {
                    # 查询转换了的用户id数据
                    $userRes = Users::get(['u_id' => $ticketRes['o_buyer_iden']]);
                    if (!empty($userRes)) {
                        if ($userRes['notify_status'] === 1) {
                            # 获取发送的网址
                            $url = $userRes['notify_url'];
                            # 判断获取数据
                            $signData         = isset($checkTicket['second']) ? $checkTicket['second'] : $checkTicket['first'];
                            $signData['t_sn'] = $tSn;
                            unset($signData['stationId']);
                            # 整理数据  获取签名
                            $signInfo = $this->encryptionSign($signData, $userRes['u_key']);
                        }
                    }
                }
            }
        }

        # 判断发送网址还有发送数据不为空的时候
        if (!empty($url) && !empty($signInfo)) {
            # 发送数据
            Curl::getInstance()->send($url, $signInfo);
        }
    }


    // 检测校验码是否正确
    function check_code($code = '', $ticketNo = '')
    {
        # 检测是否为空
        if (empty($code) || empty($ticketNo)) {
            Api::json('300', [], '校验码获取车票号为空');
        }
        # 根据车票号生产校验码
        $creatCode = substr(($ticketNo * 123456) + 789, -4);

        # 判断校验码是否正确
        if ($creatCode === $code) {
            return TRUE;
        }
        return FALSE;

    }
}