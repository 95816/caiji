<?php
/**
 * Created by PhpStorm.
 * User: Li Ning
 * Date: 2018/12/10
 * Time: 11:02
 */

namespace app\caiji\controller;

use app\caiji\model\TvChild;
use app\index\model\TxVideos;
use QL\QueryList;

class Query
{
    protected $q;

    /*
     * 采集腾讯
     */
    public function query_qq()
    {
        $this->q = new QueryList();
        // 采集页码数
        $page = request()->get('page') ? request()->get('page') : 0;
        // 采集类型
        $type = request()->get('type') ? request()->get('type') : 'movie';
        $offset = $page * 30;
        $url = 'http://v.qq.com/x/list/' . $type . '?&offset=' . $offset;
        $rules = [
            'copyfrom' => ['.figure', 'href'],
            'thumb' => ['.figure>img', 'r-lazyload'],
            'summary' => ['.figure_info', 'text'],
            'num' => ['.num', 'text'],
        ];
        $data = $this->q->get($url)->rules($rules)->query()->getData(function ($item, $type) use ($type) {
            $func = 'handle_qq_' . $type;
            if ($res = $this->$func($item['copyfrom'])) {
                $item['publish_date'] = $res['publish_date'];
                $item['type_name'] = $res['type_name'];
                $item['type_id'] = !empty($res['type_id']) ? $res['type_id'] : '1';
                $item['title'] = $res['title'];
                $item['title_en'] = !empty($res['title_en']) ? $res['title_en'] : '';
                $item['video_ids'] = !empty($res['video_ids']) ? $res['video_ids'] : '';
                $item['leading_actor'] = implode(' ', $res['leading_actor']);
                $item['second_title'] = $res['second_title'];
                $item['area_name'] = $res['area_name'];
                $item['year'] = $res['year'];
                $item['langue'] = !empty($res['langue']) ? $res['langue'] : '未知';
                $item['description'] = $res['description'];
                $item['director'] = !empty($res['director']) ? $res['director'] : '未知';
                $item['score'] = !empty($res['score']) ? $res['score'] : rand(6, 9) . '.' . rand(0, 9);
                $item['subtype'] = $res['subtype'];
                $item['current_num'] = !empty($res['video_ids']) ? count($res['video_ids']) : '';
            }
            return $item;
        })->all();
        foreach ($data as $key => &$val) {
            $video_ids = !empty($val['video_ids']) ? $val['video_ids'] : '';
            unset($val['video_ids']);
            // type_id =1 是电影,2是电视剧
            if (empty($val['type_id'])) continue;
            if ($val['type_id'] == 1) {
                //查询数据库是否存在,存在则跳过
                if (!TxVideos::get(['copyfrom' => $val['copyfrom'], 'title' => $val['title']])) {
                    TxVideos::create($val);
                }
            } elseif ($val['type_id'] == 2) {
                if (empty($video_ids)) continue;
                //查询主表数据库是否存在,存在则跳过
                if ($res = TxVideos::get(['copyfrom' => $val['copyfrom'], 'title' => $val['title']])) {
                    //更新主表
                    $res->summary = !empty($val['summary']) ? $val['summary'] : '';
                    $res->score = !empty($val['score']) ? $val['score'] : '';
                    $res->num = !empty($val['num']) ? $val['num'] : '';
                    $res->current_num = $val['current_num'];
                    $res->save();
                    //当集数有所更新是,那么重新写入
                    if (count($video_ids) > $res->current_num) {
                        // 删除
                        TvChild::where('tv_id', $res->id)->delete();
                        // 重新写入附表
                        $this->insert_tv_child($video_ids, $res->id);
                    }
                } else {
                    $video = TxVideos::create($val);
                    $this->insert_tv_child($video_ids, $video->id);
                }
            }
        }
        echo 'success';
    }

    private function insert_tv_child($video_ids, $tv_id)
    {
        $arr = array();
        foreach ($video_ids as $v) {
            $child_data['tv_id'] = $tv_id;
            $child_data['child_id'] = $v;
            $arr[] = $child_data;
        }
        $TvChild = new TvChild();
        $TvChild->saveAll($arr);
    }

    // 处理电影
    public function handle_qq_movie($url)
    {
        $html = file_get_contents($url);
        preg_match('/var COVER_INFO = (.*)/i', $html, $match);
        if (!empty($match[1])) {
            $res = json_decode($match[1], true);
            $data['publish_date'] = $res['publish_date'];
            $data['type_name'] = $res['type_name'];
            $data['type_id'] = !empty($res['typeid']) ? $res['typeid'] : '1';
            $data['title'] = $res['title'];
            $data['title_en'] = $res['title_en'];
            $data['leading_actor'] = $res['leading_actor'];
            $data['second_title'] = $res['second_title'];
            $data['area_name'] = $res['area_name'];
            $data['subtype'] = implode(',', $res['subtype']);
            $data['year'] = $res['year'];
            $data['langue'] = $res['langue'];
            $data['description'] = $res['description'];
            if (!empty($res['director']))
                $data['director'] = implode(',', $res['director']);
            if (!empty($res['score']) && !empty($res['score']['score']))
                $data['score'] = $res['score']['score'];
            return $data;
        }

    }

    // 处理电视剧
    public function handle_qq_tv($url)
    {
        $html = file_get_contents($url);
        preg_match('/var COVER_INFO = (.*)/i', $html, $match);

        if (!empty($match[1])) {
            $res = json_decode($match[1], true);
            $data['publish_date'] = $res['publish_date'];
            $data['type_name'] = $res['type_name'];
            $data['type_id'] = !empty($res['typeid']) ? $res['typeid'] : '2';
            $data['title'] = $res['title'];
            $data['video_ids'] = $this->get_vip_ids($res['vip_ids']); // 这里使用vip_ids,F=2代表都可以看,=7会员可看.=0无会员预告,=4预告(支取2,7)
            $data['leading_actor'] = $res['leading_actor'];
            $data['second_title'] = $res['second_title'];
            $data['area_name'] = $res['area_name'];
            $data['subtype'] = implode(',', $res['subtype']);
            $data['year'] = $res['year'];
            $data['langue'] = $res['langue'];
            $data['description'] = $res['description'];
            if (!empty($res['director']))
                $data['director'] = implode(',', $res['director']);
            if (!empty($res['score']) && !empty($res['score']['score']))
                $data['score'] = $res['score']['score'];
            return $data;

        }
    }

    private function get_vip_ids($vip_ids)
    {
        $resArr = [];
        foreach ($vip_ids as $vid) {
            if ($vid['F'] == 2 || $vid['F'] == 7) {
                $resArr[] = $vid['V'];
            }
        }
        return $resArr;
    }
}