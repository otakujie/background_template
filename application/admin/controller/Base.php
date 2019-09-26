<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2019/4/23
 * Time: 17:39
 */

namespace app\admin\controller;


use think\Controller;
use think\Request;

class Base extends Controller
{

    public function __construct(Request $request = NULL)
    {
        parent::__construct($request);

        // 验证权限
        $this->request = $request;
    }
}