<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 10:19
 */

namespace app\common\model;


use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

class Users extends Model
{
    protected $autoWriteTimestamp = TRUE;

    function getAuth($u_id): array
    {
        try {
            $info = $this->field('u_auth,role_id')->find(['u_id' => $u_id]);
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (!empty($info)) {

            // 角色权限
            try {
                $roleIds = explode(',', $info->toArray()['role_id']);
            } catch (Exception $e) {
            }

            if (!empty($roleIds)) {
                $role = new Roles;
                // 如果有包含id为1的role  则是管理员 返回全部
                if (in_array('1', $roleIds)) {
                    $auths = new Auths();
                    $list  = $auths->select();
                    return (array) $list;
                }
                $where = [
                    'role_id' => ['IN', $roleIds],
                ];
                try {
                    $roleList = $role->where($where)->field('role_auth')->select();
                } catch (DataNotFoundException $e) {
                } catch (ModelNotFoundException $e) {
                } catch (DbException $e) {
                }
            }

            // 个人权限
            try {
                $authIds = explode(',', $info->toArray()['u_auth']);

                // 如果有角色权限 则合并
                if (!empty($roleList)) {
                    foreach ($roleList as $row) {
                        $tem     = explode(',', $row['role_auth']);
                        $authIds = array_merge($authIds, $tem);
                    }
                }
                $authIds = array_unique($authIds);
            } catch (Exception $e) {
            }

            if (!empty($authIds)) {
                $auths = new Auths();
                try {
                    $list = $auths->where(['auth_id' => ['IN', $authIds]])->select();
                    return (array) $list;
                } catch (DataNotFoundException $e) {
                } catch (ModelNotFoundException $e) {
                } catch (DbException $e) {
                }
            }

        }
        return [];

    }

    /**
     * 添加新用户
     * @param array $data
     * @return bool
     */
    public function addUser(array $data)
    {
        # 开启事务
        $this->startTrans();

        if (!isset($data['u_account'])) {
            $System            = new System();
            $data['u_account'] = $System->getOneAccount();
        }

        $addRes = $this->allowField(TRUE)->data($data)->save();

        # 查询p_u_id的层级链，加上自己的id
        $pUIdWhere = ['u_id' => $data['p_u_id']];

        try {
            $pUIdInfo = $this->where($pUIdWhere)->field('u_level_line,p_u_id')->find();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        $updateRes = $this->isUpdate(TRUE)->save(['u_level_line' => $pUIdInfo['u_level_line'].'-'.$this->getAttr('u_id')]);

        # 记录
        // $recorder = [];

        if ($addRes && $updateRes) {
            # 如果记录器中有一条失败则回滚
            // foreach ($recorder as $value){
            //     if (!$value || $value == 0) {
            //         try {
            //             $this->rollback();
            //         } catch (PDOException $e) {
            //         }
            //         return false;
            //     }
            // }
            try {
                $this->commit();
            } catch (PDOException $e) {
            }
            return TRUE;
        } else {
            try {
                $this->rollback();
            } catch (PDOException $e) {
            }
            return FALSE;
        }
    }

    /**
     * 获取直属用户
     * @param $u_id
     * @param array $with
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    function getDirectlyChild($u_id, $with = [])
    {
        $model = new Users();
        $list  = [];

        if (!empty($with['limit'])) {
            $model->limit($with['limit']);
        }
        if (!empty($with['order'])) {
            $model->order($with['order']);
        }
        if (!empty($with['field'])) {
            $model->field($with['field']);
        }

        try {
            $list = $model->where(['p_u_id' => $u_id, 'u_status' => '1'])->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (isset($with['get_array'])) {
            $return = [];
            foreach ($list as $row) {
                $return[] = $row->toArray();
            }
            $list = $return;
        }
        return $list;
    }

    /**
     * 获取所有用户 无限级
     * @param $u_id
     * @param array $array
     * @return array|false|\PDOStatement|string|\think\Collection
     */
    public function getAllChild($u_id, array $with)
    {

        // u_id的info
        $u_idModel = new Users();
        $u_idInfo  = $u_idModel->where(['u_id' => $u_id])->field('u_level_line')->find();

        if (empty($u_idInfo)) return [];

        $model = new Users();
        $list  = [];

        if (!empty($with['limit'])) {
            $model->limit($with['limit']);
        }
        if (!empty($with['order'])) {
            $model->order($with['order']);
        }
        if (!empty($with['field'])) {
            $model->field($with['field']);
        }


        try {
            $list = $model->where(['u_level_line' => ['LIKE', $u_idInfo['u_level_line']."-%"], 'u_status' => '1'])->select();
        } catch (DataNotFoundException $e) {
        } catch (ModelNotFoundException $e) {
        } catch (DbException $e) {
        }

        if (isset($with['get_array'])) {
            $return = [];
            foreach ($list as $row) {
                $return[] = $row->toArray();
            }
            $list = $return;
        }
        return $list;
    }


    # 获取对应的站点
    function stations()
    {
        return $this->hasOne('Stations', 's_id', 'u_station');
    }
}