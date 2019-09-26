<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 16:48
 */

namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class Roles extends Model
{
    public function _getMinForId($id)
    {
        try {
            $level = $this->where('role_id', $id)->field('role_id,level,role_name')->find();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }
        if (!isset($level)) return null;

        // level越大 代表等级越小
        try {
            $res = (new self())->where('level', '>', $level->getAttr('level'))->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (!isset($res)) return null;

        return $res;

        // 测试低角色列表
        // $test = (new Roles)->_getMinForId($this->token['role_id']);
        // halt($test);
    }
}