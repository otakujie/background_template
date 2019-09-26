<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/24
 * Time: 11:48
 */

namespace app\api\controller;


use app\common\model\Roles;
use app\common\model\System;
use Siam\Api;
use think\exception\DbException;

class Role extends Base
{
    function add()
    {
        $model = new Roles();
        // 查询出最高级别的level
        $maxLevel = $model->max('level');

        $res = $model->save([
            'role_name' => $this->request->param('role_name'),
            'role_auth' => $this->request->param('role_auth'),
            'level'     => ++$maxLevel,
        ]);

        if ($res){

            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function edi()
    {
        $model = new Roles();
        $res = $model->isUpdate(true)->save([
            'role_name' => $this->request->param('role_name'),
            'role_auth' => $this->request->param('role_auth'),
        ], ['role_id' =>  $this->request->param('role_id')]);

        if ($res){
            Api::json('200', [], 'SUCCESS');
        }
        Api::json('500', [], 'ERROR');
    }

    function order_update()
    {
        $order = $this->request->param('order');

        if (empty($order)) Api::json('400', [], 'PARAMETERS_INVALID');

        $orderArr = json_decode($order, true);

        $user = new Roles();
        $data = [];

        foreach ($orderArr as $key => $row){
            $data[] = ['role_id' => $row['role_id'], 'level'=>$key];
        }
        try {
            $res = $user->saveAll($data);
            if ($res){
                Api::json('200', [], 'SUCCESS');
            }
        } catch (\Exception $e) {
        }

        Api::json('500', [], 'ERROR');
    }

}