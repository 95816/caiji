<?php
/**
 * Created by PhpStorm.
 * User: Li Ning
 * Date: 2018/12/5
 * Time: 16:42
 */

namespace app\index\controller;


use app\index\model\TxVideos;
use think\Controller;

class Video extends Controller
{
    public function txList()
    {
        $list = TxVideos::paginate(30);
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function show($id)
    {
        $data = TxVideos::get($id, true);
        //获取最新10条
        $hot_mvs = TxVideos::order('score', 'desc')->limit(10)->select();
        $this->assign('data', $data);
        $this->assign('hot_mvs', $hot_mvs);
        return $this->fetch();
    }

    public function play($id)
    {
        $data = TxVideos::get($id, true);
        //获取最新10条
        $new_mvs = TxVideos::order('publish_date', 'desc')->limit(10)->select();
        $this->assign('data', $data);
        $this->assign('new_mvs', $new_mvs);
//        echo '<pre>';
//        print_r($data);
        return $this->fetch();

    }

    public function getUrl()
    {
        if (request()->isPost()) {
            $io = request()->post('io');
            $res = [
                'code' => 200,
                'status' => 1,
                'url' => config('setting.' . $io),
                'msg' => '不要整天研究人家接口了抓紧做站去吧!'
            ];
        } else {
            $res = [
                'code' => 403,
                'status' => 0,
                'msg' => '不要整天研究人家接口了抓紧做站去吧!'
            ];
        }
        return json($res);
    }


}