<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 14:26
 */

namespace app\common\model;


use Siam\Component\Singleton;

class Menu
{
    use Singleton;

    private $auth_list;
    /**
     * 是否只返回菜单选项，默认false 也返回权限
     * @var bool
     */
    public $onlyMenu = false;

    function get($u_id)
    {
        // 先 u_id 查询 分析权限  角色权限+个人权限
        $Users = new Users();
        $lists = $Users->getAuth($u_id);

        $newList = [];

        foreach ($lists as $key => $value){
            $newList[$value['auth_id']] = $value->toArray();
        }

        $this->auth_list = $newList;

        // 再查出排序
        $System = System::where(['id' => 1])->field('auth_order')->find()->toArray();
        $order  = json_decode($System['auth_order'], TRUE);
        $return = $this->makeTree($order);

        return $return;
    }

    private function makeTree($child):array
    {
        $return = [];
        foreach ($child as $key => $value){
            // 未有权限
            if ( empty($this->auth_list[$value['id']] )){
                continue;
            }
            // 如果只需要获取菜单
            if (true == $this->onlyMenu){
                if ($this->auth_list[$value['id']]['auth_type'] == '1'){
                    continue;
                }
            }
            $tem = $this->auth_list[$value['id']];
            if ( isset($value['child']) ){
                $tem['child'] = $this->makeTree($value['child']);
            }
            $return[] = $tem;
        }
        return $return;
    }
}