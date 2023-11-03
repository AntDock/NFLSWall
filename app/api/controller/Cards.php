<?php

namespace app\api\controller;

use think\facade\Request;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Config;

use app\api\validate\Cards as CardsValidate;
use app\api\validate\CardsSetting as CardsValidateSetting;

use app\common\Common;
use app\common\Export;
use app\common\BackEnd;

class Cards extends Common
{

    //中间件
    protected $middleware = [
        \app\api\middleware\AdminAuthCheck::class => [
            'only' => [
                'Edit',
                'Delet'
            ]
        ],
        \app\api\middleware\AdminPowerCheck::class => [
            'only' => [
                'Setting'
            ]
        ],
        \app\api\middleware\SessionDebounce::class => [
            'only' => [
                'Add'
            ]
        ],
        \app\api\middleware\GeetestCheck::class => [
            'only' => [
                'Add'
            ]
        ],
    ];

    //操作函数
    protected function CAndU($id, $data, $method)
    {
        // 获取数据
        foreach ($data as $k => $v) {
            if ($v != '#') {
                $Datas[$k] = $v;
            }
        }

        // 返回结果
        function FunResult($status, $msg, $id = '')
        {
            return [
                'status' => $status,
                'msg' => $msg,
                'id' => $id
            ];
        }

        // 数据校验
        switch ((int)$Datas['model']) {
                //
            case 1:
                try {
                    validate(CardsValidate::class)
                        ->batch(true)
                        ->remove('taName', 'require')
                        ->check($Datas);
                } catch (ValidateException $e) {
                    $validateerror = $e->getError();
                    return FunResult(false, $validateerror);
                }
                break;
                //默认
            default:
                try {
                    validate(CardsValidate::class)
                        ->batch(true)
                        ->check($Datas);
                } catch (ValidateException $e) {
                    $validateerror = $e->getError();
                    return FunResult(false, $validateerror);
                }
                break;
        }

        // 启动事务
        Db::startTrans();
        try {
            //获取数据库对象
            $DbResult = Db::table('cards');
            $DbData = $Datas;
            $DbData['time'] = $this->attrGReqTime;
            $DbData['ip'] = $this->attrGReqIp;
            $DbData['img'] = '';
            $DbData['tag'] = '';
            // 方法选择
            if ($method == 'c') {
                //默认卡片状态ON/OFF:0/1
                $DbData['status'] = Config::get('lovecards.api.Cards.DefSetCardsStatus');
                $CardId = $DbResult->insertGetId($DbData); //写入并返回ID
            } else {
                //获取Cards数据库对象
                $DbResult = Db::table('cards')->where('id', $id);
                if (!$DbResult->find()) {
                    return FunResult(false, 'ID不存在');
                }
                //写入并返回ID
                $DbResult->update($DbData);
                $CardId = $id;
                //清理原始数据
                Db::table('img')->where('pid', $id)->delete();
                Db::table('cards_tag_map')->where('cid', $id)->delete();
            }

            //写入img
            $img = json_decode($Datas['img'], true);
            if (!empty($img)) {
                $JsonData = array();
                foreach ($img as $key => $value) {
                    $JsonData[$key]['aid'] = 1;
                    $JsonData[$key]['pid'] = $CardId;
                    $JsonData[$key]['url'] = $value;
                    $JsonData[$key]['time'] = $this->attrGReqTime;
                }
                Db::table('img')->insertAll($JsonData);
                //更新img视图字段
                $DbResult->where('id', $CardId)->update(['img' => $img[0]]);
            }

            //写入tag
            $tag = json_decode($Datas['tag'], true);
            if (!empty($tag)) {
                //构建数据数组
                $JsonData = array();
                foreach ($tag as $key => $value) {
                    $JsonData[$key]['cid'] = $CardId;
                    $JsonData[$key]['tid'] = $value;
                    $JsonData[$key]['time'] = $this->attrGReqTime;
                }
                Db::table('cards_tag_map')->insertAll($JsonData);
                //更新tag视图字段
                $DbResult->where('id', $CardId)->update(['tag' => Json_encode($tag)]);
            }

            // 提交事务
            Db::commit();
            return FunResult(true, '操作成功', $CardId);
        } catch (\Exception $e) {
            // 回滚事务
            dd($e);
            Db::rollback();
            return FunResult(false, '操作失败');
        }
    }

    //添加-POST
    public function Add()
    {
        $result = self::CAndU('', [
            'content' => Request::param('content'),

            'woName' => Request::param('woName'),
            'woContact' => Request::param('woContact'),
            'taName' => Request::param('taName'),
            'taContact' => Request::param('taContact'),

            'tag' => Request::param('tag'),
            'img' => Request::param('img'),

            'model' => Request::param('model'),
            'status' => Config::get('lovecards.api.Cards.DefSetCardsStatus')
        ], 'c');

        if ($result['status']) {
            if (Config::get('lovecards.api.Cards.DefSetCardsStatus')) {
                return Export::mObjectEasyCreate('', '添加成功,等待审核', 201);
            } else {
                return Export::mObjectEasyCreate(['id' => $result['id']], '添加成功', 200);
            }
        } else {
            return Export::mObjectEasyCreate($result['msg'], '添加失败', 500);
        }
    }

    //编辑-POST
    public function Edit()
    {

        $result = self::CAndU(Request::param('id'), [
            'content' => Request::param('content'),

            'woName' => Request::param('woName'),
            'woContact' => Request::param('woContact'),
            'taName' => Request::param('taName'),
            'taContact' => Request::param('taContact'),

            'tag' => Request::param('tag'),
            'img' => Request::param('img'),

            'top' => Request::param('top'),
            'model' => Request::param('model'),
            'status' => Request::param('status')
        ], 'u');

        if ($result['status']) {
            return Export::mObjectEasyCreate(['id' => $result['id']], '编辑成功', 200);
        } else {
            return Export::mObjectEasyCreate($result['msg'], '编辑失败', 500);
        }
    }

    //删除-POST
    public function Delete()
    {

        //获取数据
        $id = Request::param('id');

        //获取Cards数据库对象
        $result = Db::table('cards')->where('id', $id);
        if (!$result->find()) {
            return Export::mObjectEasyCreate([], 'id不存在', 400);
        }
        $result->delete();

        //获取img数据库对象
        $result = Db::table('img')->where('pid', $id);
        if ($result->find()) {
            $result->delete();
        }

        //获取tag数据库对象
        $result = Db::table('cards_tag_map')->where('cid', $id);
        if ($result->find()) {
            $result->delete();
        }

        //获取comments数据库对象
        $result = Db::table('cards_comments')->where('cid', $id);
        if ($result->find()) {
            $result->delete();
        }

        //返回数据
        return Export::mObjectEasyCreate([], '删除成功', 200);
    }

    //设置-POST
    public function Setting()
    {

        $data = [
            'DefSetCardsImgNum' => Request::param('DefSetCardsImgNum'),
            'DefSetCardsTagNum' => Request::param('DefSetCardsTagNum'),
            'DefSetCardsStatus' => Request::param('DefSetCardsStatus'),
            'DefSetCardsImgSize' => Request::param('DefSetCardsImgSize'),
            'DefSetCardsCommentsStatus' => Request::param('DefSetCardsCommentsStatus')
        ];

        // 数据校验
        try {
            validate(CardsValidateSetting::class)
                ->batch(true)
                ->check($data);
        } catch (ValidateException $e) {
            $validateerror = $e->getError();
            return Export::mObjectEasyCreate($validateerror, '修改失败', 400);
        }

        $result = BackEnd::mBoolCoverConfig('lovecards', $data, true);

        if ($result == true) {
            return Export::mObjectEasyCreate([], '修改成功', 200);
        } else {
            return Export::mObjectEasyCreate([], '修改失败，请重试', 400);
        }
    }

    //点赞-POST
    public function Good()
    {
        //获取数据
        $id = Request::param('id');
        $ip = $this->attrGReqIp;
        $time = $this->attrGReqTime;

        //获取Cards数据库对象
        $resultCards = Db::table('cards')->where('id', $id);
        $resultCardsData = $resultCards->find();
        if (!$resultCardsData) {
            return Export::mObjectEasyCreate([], 'id不存在', 400);
        }

        //获取good数据库对象
        $resultGood = Db::table('good');
        if ($resultGood->where('pid', $id)->where('ip', $ip)->find()) {
            return Export::mObjectEasyCreate(['tip' => '请勿重复点赞'], '点赞失败', 400);
        }

        //更新视图字段
        if (!$resultCards->inc('good')->update()) {
            return Export::mObjectEasyCreate(['cards.good' => 'cards.good更新失败'], '点赞失败', 400);
        };

        $data = ['aid' => '1', 'pid' => $id, 'ip' => $ip, 'time' => $time];
        if (!$resultGood->insert($data)) {
            return Export::mObjectEasyCreate(['good' => 'good写入失败'], '点赞失败', 400);
        };

        //返回数据
        return Export::mObjectEasyCreate(['Num' => $resultCardsData['good'] + 1], '点赞成功', 200);
    }
}
