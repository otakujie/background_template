<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/11
 * Time: 18:31
 */

namespace app\power_bank\controller;

use app\power_bank\model\PowerUsers;
use think\Cache;
use think\Request;
use Siam\Curl;

class WeChat
{

    # 公众号的appid
    const APPID = 'wx29852675b7658fd4';
    const STATE = '65354546';
    const SECRET = '70279f2e9b89d469b310233782933c74';
    # 请求微信数据的网址
    const WECHAT_HOST_OPEN = "https://open.weixin.qq.com/";
    const WECHAT_HOST_MCH = "https://api.mch.weixin.qq.com/";
    const WECHAT_HOST = "https://api.weixin.qq.com/";

    /**
     * 获取code值的方法
     * @param string $redirect_uri 回调地址
     */
    private function getCode($redirect_uri = '')
    {
        # 返回地址
        if (empty($redirect_uri)) {
            $redirect_uri = Request::instance()->url(TRUE);
        }
        # 参数
        $parameter = [
            'appid'         => self::APPID,
            'redirect_uri'  => urlEncode($redirect_uri),
            'response_type' => 'code',
            'scope'         => 'snsapi_userinfo',
            'state'         => self::STATE,
        ];
        # 拼接网址
        $url = $this->joinUrl(self::WECHAT_HOST_OPEN.'connect/oauth2/authorize', $parameter);
        # 跳转请求
        header("Location:".$url);
        exit;
    }

    /**
     * 获取用户的openid和token
     * @param string $code 用户的授权
     * @throws \Exception
     */
    function getOpenid($code = '')
    {
        # 判断code是否为空
        if (empty($code)) {
            $this->getCode();
        } else {
            # 拉取用户的openid
            $parameter = [
                'appid'      => self::APPID,
                'secret'     => self::SECRET,
                'code'       => $code,
                'grant_type' => 'authorization_code',
            ];
            # 拼接网址
            $url = $this->joinUrl(self::WECHAT_HOST.'sns/oauth2/access_token', $parameter);
            # 请求返回数据
            $curlJson = Curl::getInstance()->send($url);
            # 转换数据格式
            $curlArray = json_decode($curlJson, TRUE);
            # 缓存并返回数据
            Cache::set($code, $curlArray['openid'], $curlArray['expires_in']);
            return $curlArray['openid'];
        }
    }

    # 获取token值
    function getToken()
    {
        # 条件
        $parameter = [
            'grant_type' => 'client_credential',
            'appid'      => self::APPID,
            'secret'     => self::SECRET,
        ];
        # 拼接网址
        $url = $this->joinUrl(self::WECHAT_HOST.'cgi-bin/token', $parameter);
        # 请求获取返回数据
        $curlJson = Curl::getInstance()->send($url);
        # 转换格式
        $curlArray = json_decode($curlJson, TRUE);
        # 缓存并且返回token值
        Cache::set('token', $curlArray['access_token'], $curlArray['expires_in']);
        return $curlArray['access_token'];
    }

    /**
     * 拉取用户的信息
     * @param string $code code值
     * @param string $openid 用户标识
     * @param string $company 公司标识
     * @param string $lang 语言
     * @return string
     * @throws \Exception
     */
    function pullInformation($code = '', $openid = '', $company = '', $lang = 'zh_CN')
    {
        # 判断获取到的数据是否为空
        if (empty($openid)) {
            # 通过code获取缓存中的openid
            $openid = Cache::get($code, NULL);
            # 登录状态失效的时候重新登录
            if (empty($openid)) {
                $openid = $this->getOpenid($code);
            }
        }
        # 通过用户标识获取缓存的token
        $token = $this->getToken();

        # 拉取用户的全部信息的条件
        $parameter = [
            'access_token' => $token,
            'openid'       => $openid,
            'lang'         => $lang,
        ];
        # 拼接网址
        $url = $this->joinUrl(self::WECHAT_HOST.'cgi-bin/user/info', $parameter);
        # 请求获取数据
        $curlJson = Curl::getInstance()->send($url);
        # 转换格式
        $curlArray = json_decode($curlJson, TRUE);
        # 存储拉取到的用户数据
        # 判断用户信息是否获取到
        if (isset($curlArray['subscribe'])) {
            # 更新用户信息的数据
            $powerUsersModel = new PowerUsers();

            # 查询用户是否存在
            $powerUsersModel->data = [
                'where' => [
                    'subopenid' => $curlArray['openid'],
                ],
            ];
            $userRes               = $powerUsersModel->getOne();
            if (empty($userRes)) {
                # 用户信息为空的时候
                $updata[]              = [
                    'nickname'   => $this->filtration($curlArray['nickname'] ?? ''),
                    'subopenid'  => $curlArray['openid'],
                    'unionid'    => $curlArray['unionid'] ?? '',
                    'headimgurl' => $curlArray['headimgurl'] ?? '',
                    'company'    => $company ?? '0',
                ];
                $powerUsersModel->data = $updata;
            } else {
                # 用户信息不为空
                $updata = [
                    // 'nickname'   => $this->filtration($curlArray['nickname'] ?? '') ?? $userRes['nickname'],
                    'nickname'   => !empty($userRes['nickname']) ? $userRes['nickname'] : $this->filtration($curlArray['nickname'] ?? ''),
                    // 'unionid'    => ($curlArray['unionid'] ?? '') ?? $userRes['unionid'],
                    'unionid'    => !empty($userRes['unionid']) ? $userRes['unionid'] : ($curlArray['unionid'] ?? ''),
                    // 'headimgurl' => ($curlArray['headimgurl'] ?? '') ?? $userRes['headimgurl'],
                    'headimgurl' => !empty($userRes['headimgurl']) ? $userRes['headimgurl'] : ($curlArray['headimgurl'] ?? ''),
                ];
                # 数据
                $powerUsersModel->data = [
                    'data'  => $updata,
                    'where' => [
                        'subopenid' => $curlArray['openid'],
                    ],
                ];
            }

            $powerUsersModel->updateMore();
        }

        # 判断该用户是否已经关注了
        if (isset($curlArray['subscribe']) && $curlArray['subscribe'] === 1) {
            # 已经关注
            return '1';
        } else {
            # 没有关注
            return '0';
        }
    }

    /**
     * @param string $url 要转换的网址
     */
    function shorturl($url = '')
    {
        # 获取token
        $token = $this->getToken();

        # 发送微信转换成短连接
        $wechatUrl = self::WECHAT_HOST.'cgi-bin/shorturl?access_token='.$token;
        # 发送的请求数据
        $data = [
            'action'   => 'long2short',
            'long_url' => $url,
        ];
        # 发送请求转换成短连接
        $curlRes = Curl::getInstance()->send($wechatUrl, json_encode($data, 256));
        # 转换格式
        $curlArray = json_decode($curlRes, TRUE);
        # 判断
        if ($curlArray['errcode'] === 0) {
            return ['code' => '200', 'url' => $curlArray['short_url']];
        } else {
            return ['code' => '300', 'msg' => $curlArray['errmsg']];
        }
    }

    /**
     * 生成带参数的二维码
     * @param string $parameter 二维码的参数
     * @param bool $perpetual 是否生成永久
     * @return string
     * @throws \Exception
     */
    function qrcode($parameter = '', $perpetual = FALSE)
    {
        # 通过用户标识获取缓存的token
        $token = $this->getToken();
        # 发送到微信的网址
        $wechatUrl = self::WECHAT_HOST.'cgi-bin/qrcode/create?access_token='.$token;
        # 判断是否生成永久的二维码
        $data = [];
        if (!$perpetual) {
            $data['expire_seconds'] = 2592000;
            $data['action_name']    = 'QR_STR_SCENE';
        } else {
            $data['action_name'] = 'QR_LIMIT_STR_SCENE';
        }
        $data['action_info'] = ['scene' => ['scene_str' => $parameter]];
        # 发送请求数据
        $curlRes = Curl::getInstance()->send($wechatUrl, json_encode($data, 256));
        # 转换格式
        $curlArray = json_decode($curlRes, TRUE);
        # 返回链接
        return isset($curlArray['url']) ? $curlArray['url'] : '';
    }

    /**
     * 推送开门信息模板
     * @param array $sendData 数据
     */
    function send($sendData = [])
    {
        # 通过用户标识获取缓存的token
        $token = $this->getToken();
        # 获取链接
        $wechatUrl = self::WECHAT_HOST.'cgi-bin/message/custom/send?access_token='.$token;
        # 发送数据
        $data = [
            'touser'  => $sendData['openid'],
            'msgtype' => 'text',
            'text'    => [
                'content' => '<a href="'.$sendData['url'].'">点击进入租借充电宝</a>',
            ],
        ];
        # 发送数据到微信
        $res = Curl::getInstance()->send($wechatUrl, json_encode($data, 256));
        var_dump($res);
    }

    /**
     * 过滤微信昵称的特殊字符
     * @param string $str 要过滤的特殊字符
     * @return null|string|string[]
     */
    private function filtration($str = '')
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return trim($str);
    }

    /**
     * 拼接网址
     * @param string $url 网址
     * @param array $data 拼接的数组
     * @return string
     */
    private function joinUrl($url = '', $data = [])
    {
        # 拼接网址
        $url .= '?';
        foreach ($data as $key => $value) {
            $url .= $key.'='.$value.'&';
        }
        #
        return trim($url, '&');
    }
}