<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/17
 * Time: 16:32
 */

namespace app\power_bank\controller;

use Siam\Api;
use Siam\Curl;
use think\Request;
use app\power_bank\model\Machine as MachineModel;

class Machine
{
    # 获取机器的信息
    function lists()
    {
        $MachineModel = new MachineModel();
        # 获取查询的机器名称
        $mId = Request::instance()->param('m_id', NULL);
        # 判断机器标书是否为空
        if (empty($mId)) {
            Api::json('300', [], '机器数据为空');
        }
        # 查询机器的数据
        $MachineModel->data = [
            'where' => [
                'm_identification' => $mId,
            ],
        ];
        $machineRes         = $MachineModel->getOne();

        # 判断获取到的数据是否为空
        if (!empty($machineRes)) {
            Api::json('200', ['info' => ['lists' => $machineRes]], '查询成功');
        }
        Api::json('300', [], '机器数据为空');
    }

    /**
     * 判断充电宝是否全部借出
     */
    function checkCell()
    {
        $MachineModel = new MachineModel();
        # 获取查询的机器名称
        $mId = Request::instance()->param('m_id', NULL);
        # 判断机器标书是否为空
        if (empty($mId)) {
            Api::json('300', [], '机器数据为空');
        }
        # 查询机器的数据
        $MachineModel->data = [
            'where' => [
                'm_identification' => $mId,
            ],
        ];
        $machineRes         = $MachineModel->getOne()->data;
        # 转换数据格式
        $machineRes['channel_state'] = json_decode($machineRes['channel_state'], TRUE);
        # 判断数据是否存在
        $res = array_search(1, $machineRes['channel_state']);
        #
        if ($res === FALSE) {
            Api::json('300', [], '该机器充电宝已经全部借出');
        }
        Api::json('200', [], '');
    }

    function queryCharger()
    {
        $machineModel = new MachineModel();
        # 发送请求的数据
        $url = 'http://cd.kmud.net/cdb/cdb_api.php';
        # 获取要查询的机器数据
        $mId     = Request::instance()->param('m_id', NULL);
        $name    = Request::instance()->param('name', NULL);
        $company = Request::instance()->param('company', NULL);
        $sum     = (int) Request::instance()->param('sum', 6);
        # 条件
        $data     = [
            'mch'   => '100002',
            'cmd'   => 'F',
            'mid'   => $mId,
            'b_url' => 'http://c.kmud.net/qrt_ticket/power_bank/public/index.php/api/power_bank/callBack',
        ];
        $curlJson = Curl::getInstance()->send($url, $data);
        # 转换数据格式
        $curlArray = json_decode($curlJson, TRUE);

        # 判断是否存在   然后遍历修改数据
        $channelState = [];
        if (isset($curlArray['cdb'])) {
            # 根据传入的机器满存放机器的个数不同
            if ($sum === 6) {
                $channelState = [
                    5  => '0',
                    6  => '0',
                    7  => '0',
                    9  => '0',
                    10 => '0',
                    11 => '0',
                ];
            } else if ($sum === 12) {
                for ($i = 1; $i <= $sum; $i++) {
                    $channelState[$i] = '0';
                }
            }

            # 查询是否存在该机器的数据
            $machineModel->data = ['where' => ['m_identification' => $mId]];
            $machineModelRes    = $machineModel->getOne();

            foreach ($curlArray['cdb'] as $key => $value) {
                $channelState[$value['channel']] = $value['status'];
            }
            # 排序
            ksort($channelState);
            if (empty($machineModelRes)) {
                # 生成的数据
                $updataData[] = [
                    'm_name'           => $name,
                    'm_identification' => $mId,
                    'channel_state'    => json_encode($channelState, 256),
                    'company'          => $company,
                    'channel'          => $sum,
                ];
            } else {
                $updataData = [
                    'where' => [
                        'm_identification' => $mId,
                    ],
                    'data'  => [
                        'm_name'        => $name,
                        'channel_state' => json_encode($channelState, 256),
                        'company'       => $company,
                        'channel'       => $sum,
                    ],
                ];
            }

            $machineModel->data = $updataData;
            var_dump($machineModel->updateMore());
        }
    }
}