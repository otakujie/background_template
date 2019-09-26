<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/7/1
 * Time: 15:23
 */

namespace app\common\controller;


use app\common\model\Users;
use think\Cache;

class AuthTool
{
    /**
     * 根据用户id判断权限  使用方式：AuthTool::vifByUid(1, 'admin/t/a')  缓存权限30分钟
     * @param $uId
     * @param $rules
     * @return bool
     */
    public static function vifByUid($uId, $rules):bool
    {
        $cacheAuth = Cache::get('cache_auths_'.$uId);
        if ($cacheAuth === NULL || $cacheAuth === false){
            $uAuth = (new Users)->getAuth($uId);
            $cacheAuth = [];
            foreach ($uAuth as $key => $value){
                $cacheAuth[$value->auth_rules] = 1;
            }
            Cache::set('cache_auths_'.$uId, $cacheAuth, 1800);
        }
        if (array_key_exists($rules, $cacheAuth)) return true;

        // 兼容 * 通配符 假设有3段  先查询 */*/*  然后 xxxx/*/*  然后 xxxx/xxx/*
        $ex = explode('/', $rules);
        $count = count($ex);

        for ($i = 0; $i < $count; $i++){
            $rules = "";
            // 构造rules
            if ($i == 0){
                $rules = "*/*/*";
            }else{
                for ($k = 0; $k < $i; $k++){
                    $rules .= $ex[$k]."/";
                }
                for ($k = 0; $k < ($count - $i); $k++){
                    $rules .= "*/";
                }
                $rules = rtrim($rules, '/');
            }
            if (array_key_exists($rules, $cacheAuth)) return true;
        }

        return false;
    }
}