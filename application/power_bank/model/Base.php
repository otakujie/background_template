<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/11
 * Time: 18:23
 */

namespace app\power_bank\model;

use think\Exception;
use think\Model;

class Base extends Model
{
    public $data;

    # 需要写入日志的数据
    protected static $logData = NULL;

    public function setAction()
    {
        if (empty($this->data)) {
            return 500;
        }

        if (!empty($this->data['alias'])) $this->alias($this->data['alias']);
        if (!empty($this->data['field'])) $this->field($this->data['field']);
        if (!empty($this->data['order'])) $this->order($this->data['order']);
        if (!empty($this->data['where'])) $this->where($this->data['where']);
        if (!empty($this->data['join'])) $this->join($this->data['join']);
        if (!empty($this->data['page']) && !empty($this->data['limit'])) $this->page($this->data['page'], $this->data['limit']);
    }

    # 获取所有的数据
    public function getAll()
    {
        $this->setAction();
        try {
            $lists = $this->select();
            return $lists;
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
        }
    }

    # 获取单条数据
    public function getOne()
    {
        $this->setAction();
        try {
            $list = $this->find();
            return $list;
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
        }
    }

    # 获取总记录条数
    public function getCount()
    {
        $this->setAction();
        try {
            $number = $this->count();
            return $number;
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
        }
    }

    # 添加数据 --- 可兼容多条数据一起添加
    public function addMore()
    {
        try {
            $list = $this->allowField(TRUE)->insertAll($this->data);
            if ($list) {
                return TRUE;
            }
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
            return FALSE;
        }
    }

    # 修改数据 --- 可兼容多条数据一起修改
    public function updateMore()
    {
        try {
            if (!isset($this->data['where'])) {
                $res = $this->allowField(TRUE)->saveAll($this->data);
            } else {
                $res = $this->allowField(TRUE)->save($this->data['data'], $this->data['where']);
            }
            if ($res) {
                return TRUE;
            }
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
            return FALSE;
        }
    }

    # 删除数据  --- 可兼容多条同事删除
    public function deleteMore()
    {
        try {
            if (empty($this->data['where'])) {
                return '删除条件不能为空';
            }
            $res = $this->allowField(TRUE)->destroy($this->data['where']);
            if ($res) {
                return TRUE;
            }
        } catch (Exception $e) {
            self::$logData = $e;
            self::addLog();
            return FALSE;
        }
    }

    # 写入日志
    public static function addLog()
    {
        # 判断写入日志的数据是否为空
        if (is_null(self::$logData)) {
            die('日志数据不能为空');
        }
        # 把获取到的数据写入到本地日志中去
        file_put_contents('log/log.log', '---------['.date('Y-m-d H:i:s', time()).']------------'.PHP_EOL, FILE_APPEND);
        file_put_contents("log/log.log", json_encode(['code' => self::$logData->getCode(), 'msg' => self::$logData->getMessage()], 256).PHP_EOL, FILE_APPEND);
    }
}