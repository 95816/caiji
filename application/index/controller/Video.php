<?php
/**
 * Created by PhpStorm.
 * User: Li Ning
 * Date: 2018/12/5
 * Time: 16:42
 */

namespace app\index\controller;


use app\caiji\model\TvChild;
use app\index\model\TxVideos;
use think\Controller;

class Video extends Controller
{
    public function movie_list()
    {
        $q = request()->get('q') ? request()->get('q') : '';
        $type = request()->get('type_id') ? request()->get('type_id') : 1;
        $sort = request()->get('sort');
        if ($sort == 1) {
            $order = 'publish_date';
        } elseif ($sort == 2) {
            $order = 'num';
        } elseif ($sort == 3) {
            $order = 'score';
        }
        if (!empty($order)) {
            $list = TxVideos::where('type_id', $type)->where('title', 'like', '%' . $q . '%')->order($order, 'desc')->paginate(30, false, ['query' => request()->param()]);
        } else {
            $list = TxVideos::where('type_id', $type)->where('title', 'like', '%' . $q . '%')->order('id', 'asc')->paginate(30, false, ['query' => request()->param()]);
        }
        $this->assign('list', $list);
        $this->assign('q', $q);
        $this->assign('type_id', $type);
        $this->assign('sort', $sort);
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

    /*
     * id 视频ID
     * child_id 代表电视剧ID编号
     * */
    public function play($id, $child_id = null)
    {
        $data = TxVideos::with('items')->find($id, true)->toArray();
        //$data = TxVideos::get($id, true);
        //获取最新10条
        $new_mvs = TxVideos::order('publish_date', 'desc')->limit(10)->select();
        $this->assign('data', $data);
        $this->assign('child_id', $child_id);
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
            $cid = request()->post('cid');
            $copyfrom = TxVideos::where('id', $id)->value('copyfrom');
            // 获取此id的copyfrom 真实地址
            $res = [
                'code' => 200,
                'status' => 1,
                'url' => config('setting.' . $io),
                'r_url' => $copyfrom,
                'msg' => 'success'
            ];
            if (!empty($cid)) {
                $child_v_id = TvChild::where('id', $cid)->value('child_id');
                $r_url = str_replace('.html', '/' . $child_v_id . '.html', $copyfrom);
                $res['r_url'] = $r_url;
            }
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