<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/11
 * Time: 18:30
 */

namespace app\power_bank\controller;

use think\Config;
use Siam\Curl;
use think\Request;
use app\power_bank\model\BatteryBill;
use app\power_bank\model\Machine;

class PowerBank
{

    # 每小时价格 单位为元
    public $unitCost = 8;

    # 发送请求充电宝接口数据
    function borrow($mId = '')
    {
        $request = Request::instance();
        # 判断机器号是否为空
        if (empty($mId)) {
            die('机器号为空');
        }
        # 获取配置的网址
        $url = Config::get('url', NULL);
        # 生成发送的数据
        $sendData = [
            'mch'   => '100002',
            'cmd'   => 'B',
            'mid'   => $mId,
            'b_url' => $request->domain().$request->baseFile().'/power_bank/power_bank/callBack',
        ];
        # 发送请求
        $curlRes = Curl::getInstance()->send($url, $sendData);
        # 清楚所有的空格
        $curlRes = trim($curlRes);
        # 判断是否有返回数据
        if (is_string($curlRes)) {
            return $curlRes;
        }
        return FALSE;
    }

    # 充电宝回调接口
    function callBack()
    {
        # 回调的接口
        $callJson = file_get_contents('php://input');
        # 把获取到的数据写入到本地日志中去
        file_put_contents('log/battery/notifyAll.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
        file_put_contents("log/battery/notifyAll.log", $callJson.PHP_EOL, FILE_APPEND);
        # 转换数据格式
        $callArray = json_decode($callJson, TRUE);

        # 转换数据
        $callArray['data'] = json_decode($callArray['data'], TRUE);

        $batteryBillModel = new BatteryBill();
        $machineModel     = new Machine();

        # 判断返回数据是借出的数据还是归还的数据
        if ($callArray['cmd'] == "B") {

            # 先查询出改订单的借出机器
            $batteryBillModel->data = ['where' => ['cmd_id' => $callArray['cmd_id']]];
            $batteryBillRes         = $batteryBillModel->getOne();
            # 获取借出的机器
            $loanMid = $batteryBillRes->data['b_mid'];
            # 查询对应机器的数据
            $machineModel->data = ['where' => ['m_identification' => $loanMid]];
            $machineRes         = $machineModel->getOne()->data;
            # 转换数据格式
            $machineRes['channel_state'] = json_decode($machineRes['channel_state'], TRUE);
            # 判断频道是否存在
            if (isset($machineRes['channel_state'][$callArray['data']['channel']])) {
                $machineRes['channel_state'][$callArray['data']['channel']] = 0;
            } else {
                $machineRes['channel_state'][$callArray['data']['channel']] = 0;
            }
            # 重新转换成json
            $machineRes['channel_state'] = json_encode($machineRes['channel_state'], 256);
            # 修改机器的数据
            $machineModel->data = [$machineRes];
            $machineModel->updateMore();


            # 生成订单数据 --- 修改订单中的数据
            $billData = [
                'cdb_id'    => $callArray['data']['cdb_id'],
                'b_channel' => $callArray['data']['channel'],
                'b_power'   => $callArray['data']['power'],
                'b_status'  => 1,
                'out_time'  => time(),
            ];
            # 修改订单的数据
            $batteryBillModel->data = [
                'where' => [
                    'cmd_id' => $callArray['cmd_id'],
                ],
                'data'  => $billData,
            ];
            $batteryBillModel->updateMore();
        } else if ($callArray['cmd'] == "R") {
            # 获取配置中的每小时多少钱
            $unitCost = $this->unitCost * 100;

            # 先获取对应订单的数据
            $batteryBillModel->data = ['where' => ['cdb_id' => $callArray['data']['cdb_id'], 'b_status' => 1]];
            $billRes                = $batteryBillModel->getOne();

            # 判断获取到的订单数据是否为空
            if (empty($billRes)) {
                # 把获取到的数据写入到本地日志中去
                file_put_contents('log/battery/log.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
                file_put_contents("log/battery/log.log", json_encode(['code' => '订单数据为空', 'msg' => '充电宝标识:'.$callArray['data']['cdb_id']], 256).PHP_EOL, FILE_APPEND);
                echo 0;
                die;
            }

            # 生成时间戳
            $time = time();

            # 使用的时间
            $useTime = $time - $billRes['out_time'];

            # 判断是否超过界限时间 -- 超过就扣钱
            // $totalPrices = 0;
            // if ($useTime > ($this->limitTime * 60)) {
            # 计算用时
            $hoursUse = ceil($useTime / 3600);

            # 使用的价格
            $totalPrices = $hoursUse * $unitCost;
            # 判断 金额最大为50元
            if ($totalPrices >= 5000) {
                $totalPrices = 5000;
            }
            // }


            # 通过获取到的数据进行查询
            $insiderModel       = new \app\power_bank\model\Insider();
            $insiderModel->data = [
                'where' => [
                    'subopenid' => $billRes['subopenid'],
                ],
            ];
            $insiderModelRes    = $insiderModel->getOne();

            # 判断是否使用内部价格
            if (isset($insiderModelRes->data['allow'])) {
                # 内部优惠价格
                if ($insiderModelRes->data['allow'] === 1) {
                    $totalPrices = $hoursUse * 2;
                    if ($totalPrices >= 50) {
                        $totalPrices = 50;
                    }
                }
                # 原价退回
                if ($insiderModelRes->data['allow_original'] === 1) {
                    $totalPrices = 0;
                }
            }

            # 退回的金额
            $returnPrices = $billRes['b_prices'] - $totalPrices;


            # 计算需要扣除的金额
            // $totalPrices = $billRes['b_prices'] - $returnPrices;

            # 查询归还的机器数据
            $machineModel->data = ['where' => ['m_identification' => $callArray['data']['mid']]];
            $machineRes         = $machineModel->getOne()->data;
            # 转换格式
            $machineRes['channel_state'] = json_decode($machineRes['channel_state'], TRUE);
            # 判断该频道是否存在
            if (isset($machineRes['channel_state'][$callArray['data']['channel']])) {
                $machineRes['channel_state'][$callArray['data']['channel']] = 1;
            } else {
                $machineRes['channel_state'][$callArray['data']['channel']] = 1;
            }
            # 转换成json格式 -- 并且修改
            $machineRes['channel_state'] = json_encode($machineRes['channel_state'], 256);
            $machineModel->data          = [
                'data'  => [
                    'channel_state' => $machineRes['channel_state'],
                ],
                'where' => ['m_identification' => $callArray['data']['mid']],
            ];
            $machineModel->updateMore();

            # 归还的数据
            $updataData = [
                'r_mid'       => $callArray['data']['mid'],
                'r_channel'   => $callArray['data']['channel'],
                'r_power'     => $callArray['data']['power'],
                'b_status'    => 2,
                'r_prices'    => $totalPrices,
                'return_time' => $time,
            ];
            # 修改归还数据
            $batteryBillModel->data = [
                'data'  => $updataData,
                'where' => [
                    'cdb_id'   => $callArray['data']['cdb_id'],
                    'b_status' => 1,
                ],
            ];
            $res                    = $batteryBillModel->updateMore();
            # 订单数据修改成功后
            if ($res) {
                # 申请扣款 -- 判断退款金额是否大于0
                if ($returnPrices > 0) {
                    $payMent = new PayMent();
                    $payMent->refund([
                        'oSn'       => $billRes['b_sn'],
                        'refundFee' => $returnPrices,
                    ]);
                }
            }
        }
        echo 0;
        die;
    }
}