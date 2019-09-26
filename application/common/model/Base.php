<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/7/4
 * Time: 11:47
 */

namespace app\common\model;

use think\Exception;
use think\Model;

class Base extends Model
{
    // public $data;
    //
    // public function setAction()
    // {
    //     if (empty($this->data)) {
    //         return 500;
    //     }
    //
    //     if (!empty($this->data['alias'])) $this->alias($this->data['alias']);
    //     if (!empty($this->data['field'])) $this->field($this->data['field']);
    //     if (!empty($this->data['order'])) $this->order($this->data['order']);
    //     if (!empty($this->data['where'])) $this->where($this->data['where']);
    //     if (!empty($this->data['join'])) $this->join($this->data['join']);
    //     if (!empty($this->data['page']) && !empty($this->data['limit'])) $this->page($this->data['page'], $this->data['limit']);
    // }
    //
    // # 获取所有的数据
    // public function getAll()
    // {
    //     $this->setAction();
    //     try {
    //         $lists = $this->select();
    //         return $lists;
    //     } catch (Exception $e) {
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 获取单条
    // public function getOne()
    // {
    //     $this->setAction();
    //     try {
    //         $list = $this->find();
    //         return $list;
    //     } catch (Exception $e) {
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 获取总记录条数
    // public function getCount()
    // {
    //     $this->setAction();
    //     try {
    //         $number = $this->count();
    //         return $number;
    //     } catch (Exception $e) {
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 添加数据 --- 可兼容多条数据一起添加
    // public function addMore()
    // {
    //     try {
    //         $list = $this->allowField(TRUE)->saveAll($this->data);
    //         if ($list) {
    //             return TRUE;
    //         }
    //     } catch (Exception $e) {
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 修改数据  --- 可兼容多条数据一起修改
    // public function updataMore()
    // {
    //     try {
    //         if (!isset($this->data['where'])) {
    //             $res = $this->allowField(TRUE)->saveAll($this->data);
    //         } else {
    //             $res = $this->allowField(TRUE)->save($this->data['data'], $this->data['where']);
    //         }
    //         if ($res) {
    //             return TRUE;
    //         }
    //     } catch (Exception $e) {
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 存入数据表后获取自增id
    // public function getId()
    // {
    //     try {
    //         $res = $this->insertGetId($this->data);
    //         return $res;
    //     } catch (Exception $e) {
    //         var_dump($e->getMessage());die;
    //         $this->addLog($e);
    //         return FALSE;
    //     }
    // }
    //
    // # 记录错误的日志
    // public function addLog($error_log = [])
    // {
    //     if (empty($error_log)) {
    //         die('日志内容不能为空');
    //     }
    //     file_put_contents('log/daypass/logJson.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
    //     file_put_contents("log/daypass/logJson.log", json_encode(['code' => $error_log->getCode(), 'msg' => $error_log->getMessage(), 'data' => $this->data], 256).PHP_EOL, FILE_APPEND);
    // }
}