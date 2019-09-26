<?php
/**
 * Created by PhpStorm.
 * User: 15499
 * Date: 2019/9/25
 * Time: 14:22
 */

namespace app\power_bank\model;

class Insider extends Base
{
    protected $autoWriteTimestamp = TRUE;
    // 关闭自动写入update_time字段
    protected $updateTime = FALSE;
}