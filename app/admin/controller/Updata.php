<?php

namespace app\admin\controller;

use think\Request as TypeRequest;
use think\facade\View;
use think\facade\Db;

use app\common\Common;

use app\admin\BaseController;

class Updata extends BaseController
{
    //中间件
    protected $middleware = [\app\admin\middleware\AdminPowerCheck::class];

    //Index
    public function Index(TypeRequest $tDef_Request)
    {
        //基础变量
        View::assign([
            'AdminData'  => $tDef_Request->attrLDefNowAdminAllData,
            'ViewTitle'  => '系统更新'
        ]);

        //输出模板
        return View::fetch('/updata');
    }
}
