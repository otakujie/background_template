<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/11
 * Time: 18:29
 */

namespace app\power_bank\controller;

use think\Controller;
use think\Cache;
use Siam\Api;
use app\power_bank\model\Company;
use app\power_bank\model\BatteryBill;
use Endroid\QrCode\QrCode;
use app\power_bank\model\Insider;

class Index extends Controller
{

    # 每次借用充电宝的价格(元为单位)
    public static $totalPrices = 100;

    public function index()
    {
        $companyModel = new Company();
        $weChat       = new WeChat();
        # 获取用户的标识
        $mid     = $this->request->param('mId', NULL);
        $code    = $this->request->param('code', NULL);
        $company = $this->request->param('Co', NULL);
        $source  = $this->request->param('source', NULL);
        # 根据code值获取缓存的用户标识 -- 先判断code值是否存在
        if (empty($code)) {
            $openid = '';
        } else {
            $openid = Cache::get($code, NULL);
        }
        # 判断用户标识为空
        if (empty($openid)) {
            # 调用获取用户的数据接口
            $openid = $weChat->getOpenid($code);
        }
        # 获取用户是否关注
        $attention = $weChat->pullInformation($code, $openid, $company);
        # 查询公司 获取数据表中该公司的额配置文件
        $companyName = '共享充电宝';
        if (!empty($company)) {
            $companyModel->data = [
                'where' => [
                    'c_identification' => $company,
                ],
            ];
            $companyRes         = $companyModel->getOne();
            # 判断公司表示是否存在
            if (!empty($companyRes)) {
                $companyName = $companyRes->data['c_title'];
            }
        }
        # 显示页面 并且发送数据
        $this->assign('identification', $code);
        $this->assign('m_id', $mid);
        $this->assign('attention', $attention);
        $this->assign('companyName', $companyName);
        $this->assign('company', $company);
        $this->assign('source', $source);
        return view();
    }

    # 处理数据
    function dispose()
    {
        $insiderModel = new Insider();
        $payMent      = new PayMent();
        $request      = $this->request;
        # 获取数据
        $mId            = $request->param('mId', NULL);
        $identification = $request->param('identification', NULL);
        $company        = $request->param('Co', NULL);
        # 获取支付的code值
        $code = $request->param('code', NULL);

        # 判断机器号是否存在
        if (empty($mId)) {
            die('机器名称为空');
        }

        # 获取等于的用户标识
        $registerOpenid = Cache::get($identification, NULL);

        # 获取支付的用户标识
        $payOpenid = $payMent->getOpenid($code);

        # 登录状态是否存在
        if (empty($payOpenid)) {
            Api::json('600', [], '登录状态失效');
        }
        # 生成订单号
        $oSn = date('YmdHis', time()).rand(10000, 99999);

        # 先插入一条订单数据
        $billData[]             = [
            'b_sn'             => $oSn,
            'subopenid'        => $registerOpenid,
            'c_identification' => empty($company) ? 0 : $company,
            'create_time'      => time(),
        ];
        $batteryBillModel       = new BatteryBill();
        $batteryBillModel->data = $billData;
        $batteryBillModel->addMore();

        # 下单描述
        $body = '充电宝扣款';
        # 通过订单号缓存机器标识
        Cache::set($oSn, $mId);
        # 价格
        $totaFee = self::$totalPrices * 100;

        # 查询改用户是否为内部用户
        $insiderModel->data = [
            'where' => [
                'subopenid' => $registerOpenid,
            ],
        ];
        $insiderModelRes    = $insiderModel->getOne();
        # 判断数据是否存在
        if (isset($insiderModelRes->data['allow']) && $insiderModelRes->data['allow'] === 1) {
            $totaFee = $totaFee / 100;
        }

        # 统一下单
        $placeOrderRes = $payMent->placeOrder([
            'totalFee' => $totaFee,
            'openid'   => $payOpenid,
            'body'     => $body,
            'billNum'  => $oSn,
        ]);
        # 判断下单是否成功
        if (is_array($placeOrderRes)) {
            $payData = [
                'title'        => '充电宝',
                'totalFee'     => 'HK$'.($totaFee / 100),
                'body'         => $body,
                'package'      => $placeOrderRes['package'],
                'appId'        => $placeOrderRes['appId'],
                'timeStamp'    => $placeOrderRes['timeStamp'],
                'nonceStr'     => $placeOrderRes['nonceStr'],
                'signType'     => $placeOrderRes['signType'],
                'paySign'      => $placeOrderRes['paySign'],
                'redirect_url' => $request->domain().$request->baseFile().'/power_bank/index?code='.$identification.'&mId='.$mId.'&Co='.$company.'&source=payMent',
            ];
            # 支付
            $payMent->payPage($payData);
        }
        # 下单失败
        Api::json('300', [], '下单失败');
    }


    /**
     * 心跳查询用户是否关注公众号了
     */
    function heartbeat()
    {
        $weChat = new WeChat();
        # 获取查询的code值
        $code = $this->request->param('identification', NULL);
        # 查询用户的是否已经关注
        $attention = $weChat->pullInformation($code);
        # 判断是否已经关注了
        if ($attention === '0') {
            Api::json('300', [], '');
        }
        Api::json('200', [], '');
    }

    # 请求并且发送数据
    function conversionLink()
    {
        $wechat = new WeChat();
        # 获取网址
        $url      = $this->request->param('url', NULL);
        $shortUrl = $this->request->param('short_url', FALSE);
        $company  = $this->request->param('company', NULL);
        $mId      = $this->request->param('m_id', NULL);
        $icon     = $this->request->param('icon', NULL);

        # 判断是否直接通过url生成二维码
        if (empty($url)) {
            # 判断公司还有机器标识是否有
            if (is_null($company) || is_null($mId)) {
                Api::json('300', [], '必须输入完整网址或者输入公司标识和机器标识');
            }
            # 拼接成网址的网址
            $url = $this->request->domain().$this->request->baseFile().'/power_bank/index?mId='.$mId.'&Co='.$company;
        }
        # 判断是否是否需要转换成短连接
        if ($shortUrl !== FALSE) {
            # 到微信中转换成短连接
            $wechatRes = $wechat->shorturl($url);
            # 判断微信转成短连接是否成功
            if ($wechatRes['code'] === '200') {
                $url = $wechatRes['url'];
            } else {
                Api::json('300', [], $wechatRes['msg']);
            }
        }

        # 生成带参数的二维码
        $url = $wechat->qrcode($url);

        # 二维码logo的地址
        if (is_null($icon)) {
            $icon = '';
        } else {
            $data = [
                0 => $icon,
            ];
            self::urlPic($data, $company);
            $icon = 'static/images/'.$company.'.png';
        }
        # 生成二维码
        self::getQrCode($url, $icon, 270, 53);
    }

    /**
     * 生成带logo的二维码
     * @param string $code_content 二维码内容
     * @param string $code_logo 二维码logo的地址
     * @param int $code_size 二维码的大小
     * @param int $code_logo_width 二维码logo的宽度
     * @return string
     * @throws \Endroid\QrCode\Exception\InvalidPathException
     */
    public static function getQrCode($code_content = '', $code_logo = '', $code_size = 100, $code_logo_width = 20)
    {
        // 二维码内容
        $qr_code = new QrCode($code_content);
        // 二维码设置
        $qr_code->setSize($code_size);
        // 边框宽度
        $qr_code->setMargin(10);
        // 图片格式
        $qr_code->setWriterByName('png');
        // 字符编码
        $qr_code->setEncoding('UTF-8');
        // 颜色设置，前景色，背景色(默认黑白)
        $qr_code->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qr_code->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);

        // logo设置
        if ($code_logo) {
            $qr_code->setLogoPath($code_logo);
            // logo大小
            $qr_code->setLogoWidth($code_logo_width);
        }
        // 输出图片
        header('Content-Type: '.$qr_code->getContentType());
        echo $qr_code->writeString();
        exit;
    }

    /**
     * 通过发送的网址生成本地图片
     * @param array $files 图片资源或url
     * @param string $name 图片名称的开头
     * @param string $Folder 所存文件夹的名称
     */
    public static function urlPic($files = [], $name = '', $Folder = 'static/images')
    {
        $file_path = ROOT_PATH.'public'.DS.$Folder.DS;
        foreach ($files as $kk => $val) {
            $file_name = $name;
            $res       = file_put_contents($file_path.$file_name.'.png', file_get_contents($val));
            if ($res > 0) {
                //  获取图片的存放相对路径
                $data[]['file_path'] = 'public'.DS.$Folder.DS.$file_name.'.png';
            }
        }
    }

    function push()
    {
        $wecaht = new WeChat();
        # 接收网址
        $url    = $this->request->param('url', NULL);
        $openid = $this->request->param('openid', NULL);
        # 把获取到的数据写入到日志中去
        file_put_contents('log/battery/push.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
        file_put_contents("log/battery/push.log", json_encode(['url' => $url, 'openid' => $openid], 256).PHP_EOL, FILE_APPEND);

        # 格式化链接
        $url = urldecode($url);
        # 替换里面的数据
        $url = str_replace('powerbank', 'power_bank', $url);
        # 发送的数据
        $sendData = [
            'url'    => $url,
            'openid' => $openid,
        ];
        # 发送
        $wecaht->send($sendData);
    }
}