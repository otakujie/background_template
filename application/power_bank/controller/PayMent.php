<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/11
 * Time: 18:30
 */

namespace app\power_bank\controller;

use Siam\Curl;
use think\Cache;
use think\Request;
use app\power_bank\model\BatteryBill;

class PayMent
{

    # 请求的地址
    const URL = 'https://pay.hkfocusvision.com/payment';
    const MID = '614b222f96516b17d25bfecd1c248c6f';
    const MKEY = 'c2a752a0984abc548c726cc12b6bc464';

    /**
     * 获取code
     * @param string $redirect_uriredirectUri 地址
     */
    private function code($redirect_uriredirectUri = '')
    {
        # 返回地址
        if (empty($redirect_uriredirectUri)) {
            # 回调地址为空的时候自动获取当前的网址
            $request                 = Request::instance();
            $redirect_uriredirectUri = $request->url(TRUE);
        }
        # 生成时间戳
        $sTime = time();
        # 生成签名
        $sign = $this->creatSign($sTime);
        # 生成请求数据
        $sendLists = [
            's'            => 'api/pay',
            'method'       => 'getOauthCode',
            'mid'          => self::MID,
            'stime'        => $sTime,
            'sign'         => $sign,
            'redirect_uri' => Urlencode($redirect_uriredirectUri),
        ];
        # 拼接网址
        $url = $this->jointUrl(self::URL.'/public/index.php', $sendLists);
        # 跳转获取数据
        header('location:'.$url);
    }

    # 获取openid
    function getOpenid($code = '')
    {
        # 判断是否存在code
        if (empty($code)) {
            $this->code();
        } else {
            # 获取时间戳
            $sTime = time();
            # 签名
            $sign = $this->creatSign($sTime);
            # 生成发送数据
            $sendLists = [
                's'      => 'api/pay',
                'method' => 'getOpenId',
                'mid'    => self::MID,
                'stime'  => $sTime,
                'sign'   => $sign,
                'code'   => $code,
            ];
            # 拼接网址
            $url = $this->jointUrl(self::URL.'/public/index.php', $sendLists);
            # 发送请求数据
            $curlJson = Curl::getInstance()->send($url);
            # 格式换数据
            $curlArray = json_decode($curlJson, TRUE);
            # 缓存用户的openid并且缓存
            if ($curlArray['code'] === '200') {
                Cache::set($code, $curlArray['data']['openid']);
                return $curlArray['data']['openid'];
            } else {
                return FALSE;
            }
        }
    }

    /**
     * 统一下单
     * @param array $condition 统一下单的接口
     * @return bool
     * @throws \Exception
     */
    function placeOrder($condition = [])
    {
        # 判断是否存在回调接口
        if (!isset($condition['notifyUrl']) || empty($condition['notifyUrl'])) {
            $request                = Request::instance();
            $condition['notifyUrl'] = $request->domain().$request->baseFile().'/power_bank/pay_ment/callBack';
        }
        # 获取时间戳
        $sTime = time();
        # 签名
        $sign = $this->creatSign($sTime);
        # 请求数据
        $sendLists = [
            's'            => 'api/pay',
            'method'       => 'jspay',
            'mid'          => self::MID,
            'stime'        => $sTime,
            'sign'         => $sign,
            'total_fee'    => $condition['totalFee'],
            'openid'       => $condition['openid'],
            'body'         => $condition['body'],
            'out_trade_no' => $condition['billNum'],
            'notify_url'   => $condition['notifyUrl'],
        ];
        # 拼接网址
        $url = $this->jointUrl(self::URL.'/public/index.php', $sendLists);
        # 发送请求
        $curlJson = Curl::getInstance()->send($url);
        # 转换数据格式
        $curlArray = json_decode($curlJson, TRUE);

        # 判断是否支付成功
        if ($curlArray['code'] === '200') {
            return $curlArray['data'];
        } else {
            return FALSE;
        }
    }

    # 跳转支付页面
    function payPage($condition = [])
    {
        # 判断不存在或者为空的时候
        if (!isset($condition['redirect_url']) || empty($condition['redirect_url'])) {
            $request                   = Request::instance();
            $condition['redirect_url'] = $request->domain().$request->baseFile().'/power_bank/index';
        }
        # 请求条件
        $sendLists = [
            's'            => 'api/pay/direct',
            'title'        => $condition['title'],
            'total_fee'    => $condition['totalFee'],
            'body'         => $condition['body'],
            'redirect_url' => Urlencode($condition['redirect_url']),
            'package'      => $condition['package'],
            'appId'        => $condition['appId'],
            'timeStamp'    => $condition['timeStamp'],
            'nonceStr'     => $condition['nonceStr'],
            'signType'     => $condition['signType'],
            'paySign'      => $condition['paySign'],
        ];
        # 拼接网址
        $url = $this->jointUrl(self::URL.'/public/index.php', $sendLists);
        # 跳转网址
        header('Location:'.$url);
    }

    # 退款
    function refund($condition = [])
    {
        # 时间戳
        $sTime = time();
        # 签名
        $sign = $this->creatSign($sTime);
        # 退款的数据
        $refund = [
            's'          => 'api/pay',
            'method'     => 'refund',
            'mid'        => self::MID,
            'stime'      => $sTime,
            'sign'       => $sign,
            'o_sn'       => $condition['oSn'],
            'refund_fee' => $condition['refundFee'],
        ];
        # 拼接网址  然后退款
        $url = $this->jointUrl(self::URL.'/public/index.php', $refund);
        # 发送
        $curlRes = Curl::getInstance()->send($url);
        # 把获取到的数据写入到本地日志中去
        file_put_contents('log/battery/payMent.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
        file_put_contents("log/battery/payMent.log", $curlRes.PHP_EOL, FILE_APPEND);
    }

    /**
     * 拼接网址
     * @param string $url
     * @param array $data
     * @return string
     */
    private function jointUrl($url = '', $data = [])
    {
        # 判断数据是否为空
        if (empty($url) || empty($data)) {
            die('数据为空');
        }
        # 拼接网址
        $url .= '?';
        foreach ($data as $key => $value) {
            $url .= $key.'='.$value.'&';
        }
        # 返回拼接后的网址
        return trim($url, '&');
    }

    /**
     * 签名
     * @param int $time 时间戳
     * @return string
     */
    private function creatSign($time = 0)
    {
        $parameter = self::MID.self::MKEY.$time;
        # 返回签名
        return strtoupper(md5($parameter));
    }

    # 回调地址
    function callBack()
    {
        $batteryBillModel = new BatteryBill();
        # 回调的接口
        $callJson = input('param.');
        # 把获取到的数据写入到本地日志中去
        file_put_contents('log/battery/payMent.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
        file_put_contents("log/battery/payMent.log", json_encode($callJson, 256).PHP_EOL, FILE_APPEND);


        # 判断获取的支付信息是否正确
        if (isset($callJson['is_pay']) && $callJson['is_pay'] == '1') {
            # 获取机器标识
            $mId = Cache::get($callJson['o_sn']);
            # 支付成功后调用接口
            $powerBank = new PowerBank();
            $curlRes   = $powerBank->borrow($mId);

            # 生成订单数据
            $billLists = [
                'where' => [
                    'b_sn' => $callJson['o_sn'],
                ],
                'data'  => [
                    'weichat_num' => $callJson['pay_sn'],
                    'b_mid'       => $mId,
                    'b_prices'    => $callJson['total_fee'],
                    'b_status'    => 0,
                    'cmd_id'      => $curlRes,
                ],
            ];
            # 添加到数据表中
            $batteryBillModel->data = $billLists;
            $batteryBillModel->updateMore();
        }
    }
}