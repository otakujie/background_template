<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 14:45
 */

namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Model;

class System extends Model
{

    /**
     * 获取新的用户账户
     * @return string
     */
    public function getOneAccount():string
    {
        try {
            $info = $this->field('user_next_id')->where(['id' => 1])->find();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }
        // +1
        try {
            $this->where(['id' => 1])->setInc('user_next_id');
        } catch (Exception $e) {
        }

        // 获取完还要随机拼接一个 防止并发
        $info['user_next_id'] .= rand(0,9);

        return $info['user_next_id'];
    }
}