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
        /*echo '<pre>';
        print_r($list);
        die;*/
        return $this->fetch();
    }

    public function show($id)
    {
        $data = TxVideos::get($id);
        $this->assign('data', $data);

        /*echo '<pre>';
        print_r($data);die;*/
        return $this->fetch();
    }
}