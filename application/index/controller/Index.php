<?php

namespace app\index\controller;

use app\index\model\News;
use app\index\model\TxVideos;
use Jaeger\GHttp;
use QL\QueryList;
use think\Controller;
use think\Image;
use QL\Ext\CurlMulti;
use think\Request;

class Index extends Controller
{
    private $q;

    public function __construct()
    {
        parent::__construct();
        $this->q = QueryList::getInstance();
    }


    public function index(Request $request)
    {
        $page = $request->get('page') ? $request->get('page') : 11;

        $urlArr[] = 'http://xin.18183.com/news/list_906_' . $page . '.html';

        echo '开始采集第' . $page . '页' . PHP_EOL;
        echo '<pre>';
        print_r($urlArr);
        if ($page == 101) {
            echo '采集成功10页';
            die;
        }

        /*for ($i = $page; $i <= 100; $i++) {
            $urlArr[] = 'http://xin.18183.com/news/list_906_' . $i . '.html';
        }*/
        $this->q = QueryList::getInstance();


        $this->q->use(CurlMulti::class);
        // 如果 b 包含在 ai里面,那么必须要a>b才可以
        $rules = [
            'title' => ['h4>a', 'title'],
            'url' => ['dt>a', 'href'],
            'description' => ['p', 'text'],
            'img' => ['dt>a>img', 'src']
        ];

        $this->q->rules($rules)->curlMulti($urlArr)
            ->success(function (QueryList $q, CurlMulti $curl, $r) {
                // 切片选择器 (选择范围)
                $range = '.news_list>dl';
                $data = $q->range($range)->query()->getData(function ($item) {
                    if ($res = $this->handle_detail($item['url'])) {
                        $item['content'] = $res['content'];
                        $item['date'] = strtotime(str_replace('时间：', '', $res['date']));
                    }
                    $image = explode('@', $item['img']);
                    $real_name = $this->down($image[0], './images/' . date('Ymd') . '/' . basename($image[0]));
                    $item['img'] = str_replace($item['img'], $real_name, $item['img']);
                    return $item;
                });
                foreach ($data->all() as $key => &$val) {
                    $val['caiji_url'] = $val['url'];
                    $val['thumb'] = $val['img'];
                    unset($val['url']);
                    unset($val['img']);
                    //查询数据库是否存在,存在则跳过
                    if (!News::get(['caiji_url' => $val['caiji_url']])) {
                        News::create($val);
                    }
                }
                //回头继续执行
                //$this->q->destruct();
            })->start([
                // 最大并发数，这个值可以运行中动态改变。
                'maxThread' => 100,
                // 触发curl错误或用户错误之前最大重试次数，超过次数$error指定的回调会被调用。
                'maxTry' => 3,
                // 全局CURLOPT_*
                'opt' => [
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_RETURNTRANSFER => true
                ],
                // 缓存选项很容易被理解，缓存使用url来识别。如果使用缓存类库不会访问网络而是直接返回缓存。
                'cache' => ['enable' => false, 'compress' => false, 'dir' => null, 'expire' => 86400, 'verifyPost' => false]
            ]);
        $page = $page + 1;
        $this->redirect($request->baseUrl() . '?page=' . $page);

    }

    //二次处理
    public function handle_detail($url)
    {
        $this->q = new QueryList();
        $rules2 = [
            'content' => ['.content_p', 'html'],
            'date' => ['.other>span:eq(2)', 'text']
        ];
        $data = $this->q->get($url)->rules($rules2)->queryData();
        if (!empty($data)) {
            foreach ($data as $key => &$val) {
                preg_match_all("/<img.*? src=\"(.+?)\".*?>/", $val['content'], $imgs);
                if (!empty($imgs)) {
                    foreach ($imgs[1] as $img) {
                        $image = explode('@', $img);
                        $real_name = $this->down($image[0], './images/' . date('Ymd') . '/' . basename($image[0]));
                        $val['content'] = str_replace($img, $real_name, $val['content']);
                    }
                }
            }
            return $data[0];
        }
    }

    public function down($url, $filename)
    {
        if (stripos($url, "http://") === false && stripos($url, "https://") === false) {
            return substr($url, 1);
        } else {
            if ($url == "") {
                return false;
            }
            if ($filename == "") {
                $ext = strrchr($url, ".");
                $ext = strtolower($ext);
                if ($ext != ".gif" && $ext != ".jpg" && $ext != ".png") {
                    return false;
                }
                $filename = './images/' . date('Ymd') . '/';
            }
            //优先查看文件夹是否存在
            if (!is_dir(dirname($filename))) {
                mkdir(dirname($filename), 0777);
            }
            ob_start();
            $img = $this->getContentByMatch($url);
            ob_end_clean();
            $fp2 = @fopen($filename, "a");
            fwrite($fp2, $img);
            fclose($fp2);
            //执行加水印
            return $filename;
        }
    }

    public function getContentByMatch($url, $referer = null)
    {
        $referer = $referer ? $referer : $url;
        $headers[] = "Referer: $referer";
        $process = curl_init();
        curl_setopt($process, CURLOPT_URL, $url);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($process, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)');
        curl_setopt($process, CURLOPT_ENCODING, 'gzip');
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        $return = curl_exec($process);
        curl_close($process);
        return $return;
    }

    public function add_water($img_url = '')
    {
        $img_url = './images/20181112/f2e5f11d30332558382756d71c81716a.jpg';
        $img = Image::open($img_url);
        // 给原图左上角添加水印并保存water_image.png
        $img->water('./static/images/logo.png', Image::WATER_SOUTHEAST, 80)->save('water_image.png');
    }

    public function blog(Request $request)
    {
        $this->q = QueryList::getInstance();


        $this->q->use(CurlMulti::class);

        $this->q->rules([
            'title' => ['h3 a', 'text'],
            'link' => ['h3 a', 'href']
        ])->curlMulti([
            'https://github.com/trending/php',
            'https://github.com/trending/go'
        ])->success(function (QueryList $ql, CurlMulti $curl, $r) {
            echo "Current url:{$r['info']['url']} \r\n";
            $data = $ql->query()->getData();
            echo '<pre>';
            print_r($data->all());
        })->start();
        /*$img = 'https://cdn.duanliang920.com/uploads/allimg/150823/1-150R30926000-L.jpg';
        GHttp::download($img,  pathinfo(parse_url($img)['path'])['basename']);
        die;*/


    }

    public function handle_blog($url)
    {
        $rules2 = [
            'content' => ['.content', 'html'],
        ];
        $data = $this->q->get($url)->rules($rules2)->queryData();
        if (!empty($data)) {
            foreach ($data as $key => &$val) {
                preg_match_all("/<img.*? src=\"(.+?)\".*?>/", $val['content'], $imgs);
                if (!empty($imgs)) {
                    foreach ($imgs[1] as $img) {
                        $image = explode('@', $img);
                        $real_name = $this->down($image[0], './images/' . date('Ymd') . '/' . basename($image[0]));
                        $val['content'] = str_replace($img, $real_name, $val['content']);
                    }
                }
            }
            return $data[0];
        }
    }

    public function qq_vd()
    {

        $this->q = new QueryList();
        $this->q->use(CurlMulti::class);
        /*$page = 73;
        for ($i = $page; $i < $page + 3; $i++) {
            $urlArr[] = 'http://v.qq.com/x/list/movie?&offset=' . ($i * 30);
        }*/
        $page = request()->get('page');
        $offset = $page * 30;
        $urlArr[] = 'http://v.qq.com/x/list/movie?&offset=' . $offset;

        $rules = [
            'copyfrom' => ['.figure', 'href'],
            'thumb' => ['.figure>img', 'r-lazyload'],
            'summary' => ['.figure_info', 'text'],
            'num' => ['.num', 'text'],
        ];

        $this->q->rules($rules)->curlMulti($urlArr)
            ->success(function (QueryList $q, CurlMulti $curl, $r) {
                // 切片选择器 (选择范围)
                //$range = '.s-tab-main>ul';
                $data = $q->query()->getData(function ($item) {
                    if ($res = $this->handle_vipqq($item['copyfrom'])) {
                        $item['publish_date'] = $res['publish_date'];
                        $item['type_name'] = $res['type_name'];
                        $item['title'] = $res['title'];
                        $item['title_en'] = $res['title_en'];
                        $item['leading_actor'] = implode(' ', $res['leading_actor']);
                        $item['second_title'] = $res['second_title'];
                        $item['area_name'] = $res['area_name'];
                        $item['year'] = $res['year'];
                        $item['description'] = $res['description'];
                        $item['director'] = !empty($res['director']) ? $res['director'] : '未知';
                        $item['score'] = !empty($res['score']) ? $res['score'] : rand(6, 9) . '.' . rand(0, 9);
                        $item['subtype'] = $res['subtype'];
                    }
                    return $item;
                })->all();

                foreach ($data as $key => $val) {
                    //查询数据库是否存在,存在则跳过
                    if (!TxVideos::get(['copyfrom' => $val['copyfrom']])) {
                        TxVideos::create($val);
                    }
                }
                //回头继续执行
                //$this->q->destruct();
            })->start([
                // 最大并发数，这个值可以运行中动态改变。
                'maxThread' => 100,
                // 触发curl错误或用户错误之前最大重试次数，超过次数$error指定的回调会被调用。
                'maxTry' => 3,
                // 全局CURLOPT_*
                'opt' => [
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_RETURNTRANSFER => true
                ],
                // 缓存选项很容易被理解，缓存使用url来识别。如果使用缓存类库不会访问网络而是直接返回缓存。
                'cache' => ['enable' => false, 'compress' => false, 'dir' => null, 'expire' => 86400, 'verifyPost' => false]
            ]);
        echo '成功';
        die;
    }

    public function handle_vipqq($url)
    {
        $html = file_get_contents($url);
        preg_match('/var COVER_INFO = (.*)/i', $html, $match);
        if (!empty($match[1])) {
            $res = json_decode($match[1], true);
            /*echo '<pre>';
            print_r($res);
            die;*/
            $data['publish_date'] = $res['publish_date'];
            $data['type_name'] = $res['type_name'];
            $data['title'] = $res['title'];
            $data['title_en'] = $res['title_en'];
            $data['leading_actor'] = $res['leading_actor'];
            $data['second_title'] = $res['second_title'];
            $data['area_name'] = $res['area_name'];
            $data['subtype'] = implode(',', $res['subtype']);
            $data['year'] = $res['year'];
            $data['description'] = $res['description'];
            if (!empty($res['director']) && !empty($res['director'][0]))
                $data['director'] = $res['director'][0];
            if (!empty($res['score']) && !empty($res['score']['score']))
                $data['score'] = $res['score']['score'];
            return $data;
        }

    }

}
