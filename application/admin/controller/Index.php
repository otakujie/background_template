<?php
namespace app\admin\controller;

use app\common\controller\AuthTool;
use think\Session;

class Index extends Base
{
    function hello()
    {
        return "☺";
    }
    public function index()
    {
        $lang=input('lang');

        // 多语言设置
        switch ($lang) {
            case 'en':
                cookie('think_var', 'en');
                break;
            case 'zn':
                cookie('think_var', 'zh-cn');
                break;
            default:
                cookie('think_var','zh-cn');
                break;
        }

        return view('index');
    }

    function login()
    {
        return view('login');
    }
}