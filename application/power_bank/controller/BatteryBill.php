<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/12
 * Time: 12:03
 */

namespace app\power_bank\controller;

use Siam\Api;
use think\Cache;
use think\Controller;
use think\Request;
use app\power_bank\model\BatteryBill as BatteryBillModel;

class BatteryBill extends Controller
{
    # 获取对应用户标识订单数据中还未归还的数据
    function lists()
    {
        $batteryBillModel = new BatteryBillModel();
        $request          = Request::instance();
        # 获取查询的条件
        $code      = $request->param('identification', NULL);
        $page      = $request->param('page', 1);
        $limit     = $request->param('limit', 3);
        $orderData = $request->param('orderData', NULL);
        # 获取缓存的用户标识
        $openid = Cache::get($code, NULL);
        if (empty($openid)) {
            Api::json('300', [], '登录状态无效');
        }

        # 通过查询的条件 -- 正在进行的订单(充电宝已经弹出)
        if ($orderData === 'home') {
            $where = [
                'subopenid' => $openid,
                'b_status'  => 1,
            ];
        } else if ($orderData === 'transaction') {
            # 交易页面的查询条件
            $where = [
                'subopenid' => $openid,
                'b_status'  => ['>=', 0],
            ];
        } else if ($orderData === 'bill') {
            # 订单列表页面的查询条件
            $where = [
                'subopenid' => $openid,
                'b_status'  => ['NEQ', 0],
            ];
        }

        # 查询总条数
        $batteryBillModel->data = ['where' => $where];
        $count                  = $batteryBillModel->getCount();
        # 查询对应的数据
        $batteryBillModel->data = [
            'alias' => 'b',
            'where' => $where,
            'page'  => $page,
            'limit' => $limit,
            'order' => 'b.create_time DESC,b.b_status',
            'field' => 'b.b_prices,b.r_prices,b.out_time,b.return_time,ma.m_name as b_name,m.m_name as r_name',
            'join'  => [
                ['i_machine ma', 'b.b_mid = ma.m_identification', 'LEFT'],
                ['i_machine m', 'b.r_mid = m.m_identification', 'LEFT'],
            ],
        ];
        $batteryBillRes         = $batteryBillModel->getAll();

        # 格式化时间戳
        $batteryBillInfo = [];

        # 判断查询到的数据是否为空
        $sum = 0;
        if (!empty($batteryBillRes)) {
            foreach ($batteryBillRes as $key => $value) {
                $value->data['sum'] = ++$sum;
                # 格式化借出时间
                if (isset($value->data['out_time']) && $value->data['out_time'] !== NULL) {
                    $value->data['out_time'] = date('Y-m-d H:i:s', $value->data['out_time']);
                } else {
                    $value->data['out_time'] = '暂未弹出';
                }
                # 格式化归还时间
                if (isset($value['return_time']) && $value->data['return_time'] !== NULL) {
                    $value->data['return_time'] = date('Y-m-d H:i:s', $value->data['return_time']);
                } else {
                    $value['return_time'] = '暂未归还';
                }
                # 归还机器名称
                if (!isset($value['r_name']) || empty($value['r_name'])) {
                    $value['r_name'] = '暂未归还';
                }
                # 重新整合数据
                $batteryBillInfo[] = $value->data;
            }
        }


        # 判断返回数据
        if (!empty($batteryBillInfo)) {
            Api::json('200', ['info' => ['lists' => $batteryBillInfo, 'count' => $count]], '查询成功');
        }
        Api::json('300', [], '暂无数据');
    }

    # 交易记录页面
    function transactionRecord()
    {
        $code = Request::instance()->param('identification', NULL);

        $this->assign('identification', $code);

        return view();
    }

    # 订单列表
    function orderList()
    {
        $code = Request::instance()->param('identification', NULL);

        $this->assign('identification', $code);

        return view();
    }
}