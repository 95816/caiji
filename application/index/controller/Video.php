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
    public function movie_list()
    {
        $type = request()->get('type_id') ? request()->get('type_id') : 1;
        $list = TxVideos::where('type_id', $type)->paginate(30);
        $this->assign('list', $list);
        return $this->fetch('video/list');
    }

    public function show($id)
    {
        $data = TxVideos::with('items')->find($id, true)->toArray();
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
            $id = request()->post('id');
            $copyfrom = TxVideos::where('id', $id)->value('copyfrom');
            // 获取此id的copyfrom 真实地址
            $res = [
                'code' => 200,
                'status' => 1,
                'url' => $this->secret(config('setting.' . $io)),
                'r_url' => $this->secret($copyfrom),
                'msg' => 'success'
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

    // 加密
    private function secret($url, $operation = false)
    {
        $code = md5(config('setting.code'));
        $iv = substr($code, 0, 16);
        $key = substr($code, 16);
        if ($operation) {
            return openssl_decrypt(base64_decode($url), "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv);
        }
        return base64_encode(openssl_encrypt($url, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv));
    }


}