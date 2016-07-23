<?php
namespace com\huimang\ganhuo\controller\infoq;

use wisphp\db\Db;
use wisphp\Exception;
use wisphp\curl\Request;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/16 19:19
 * @copyright : 2016 huimang.com
 * @filesource: Controller.php
 */
class Controller extends \wisphp\web\http\Controller
{
    private $host;
    private $infoqHost;
    private $types = [
        'news' => 1,
        'article' => 2,
        'presentation' => 4,
    ];

    public function _before()
    {
        $this->host = 'http://'.$this->request->host;
        $this->infoqHost = 'http://www.infoq.com';
    }

    /**
     * 通过网址获取内容
     *
     * @param $url
     *
     * @return string
     * @throws Exception
     */
    private function loadContentFromUrl($url)
    {
        $md5 = md5($url);
        $path = TMP_ROOT . $md5;
        if (is_readable($path) && time() - filemtime($path) < 60 * 60) {
            $content = file_get_contents($path);
        } else {
            $curl = new Request(
                array(
                    CURLOPT_TIMEOUT => 20,
                )
            );
            $ret = $curl->get($url);
            if ($ret->code != 200) {
                throw new Exception('infoq.u_code url:' . $url . '; code:' . $ret->code);
            }
            $content = $ret->content;
            file_put_contents($path, $content);
        }
        return $content;
    }

    private function getStr(&$str, $starts, $end, $mb = false)
    {
        if ($mb) {
            $funcs = array('mb_strpos', 'mb_strlen', 'mb_substr');
        } else {
            $funcs = array('strpos', 'strlen', 'substr');
        }

        if (is_int($starts)) {
            $pos1 = $starts;
        } else {
            if (is_string($starts)) {
                $pos1 = call_user_func($funcs[0], $str, $starts) + call_user_func($funcs[1], $starts);
            } else {
                $pos1 = 0;
                foreach ($starts as $start) {
                    $pos1 = call_user_func($funcs[0], $str, $start, $pos1) + call_user_func($funcs[1], $start);
                }
            }
        }
        $pos2 = call_user_func($funcs[0], $str, $end, $pos1);
        $ret = call_user_func($funcs[2], $str, $pos1, $pos2 - $pos1);
        $str = call_user_func($funcs[2], $str, $pos2 + strlen($end));
        return $ret;
    }

    /**
     * 新闻
     */
    public function news()
    {
        $this->showItems(__FUNCTION__);
    }

    private function showItems($typeName)
    {
        $type = $this->types[$typeName];
        if (!$this->get('_of')) {
            $this->request->outputFormat = 'json';
        }
        $page = intval($this->get('_pn', 0));
        $rows = Db::get('ganhuo')
                  ->table('article')
                  ->field(
                      [
                          'article_id',
                          'title',
                          'author',
                          'translator',
                          'publish_time',
                          'original_url',
                          'comment_num',
                          'summary',
                          'cover',
                      ]
                  )
                  ->where(
                      [
                          'type' => $type,
                      ]
                  )
                  ->order('publish_time desc, article_id', true)
                  ->limit($page * 10, 10)
                  ->get();
        $items = [];
        foreach ($rows as $row) {
            $item = [
                'url' => $this->infoqHost . $row['original_url'],
                'title' => $row['title'],
                'author' => [
                    'url' => '/cn/author/' . rawurlencode($row['author']),
                    'name' => $row['author'],
                    '__LINK__' => $this->host . '/infoq/author/?url=' . rawurlencode(
                            '/cn/author/' . urlencode($row['author'])
                        ),
                ],
                'time' => date('Y年m月d日', $row['publish_time']),
                'reply' => intval($row['comment_num']),
                //'__LINK__' => $this->host . '/infoq/arti?url=' . rawurlencode($this->infoqHost . $row['original_url']),
                '__LINK__' => $this->host . '/infoq/arti2/'.$row['article_id'],
                'summary' => $row['summary'],
            ];
            if ($row['translator']) {
                $item['translator'] = [
                    'url' => '/cn/author/' . rawurlencode($row['translator']),
                    'name' => $row['translator'],
                    '__LINK__' => $this->host . '/infoq/author/?url=' . rawurlencode(
                            '/cn/author/' . urlencode($row['translator'])
                        ),
                ];
            }
            if ($row['cover']) {
                $item['cover'] = $row['cover'];
            }
            $items[] = $item;
        }
        $this->set('items', $items);
        $this->set(
            '__LINKS__', array(
                           "prev" => $page > 1 ? $this->host . '/infoq/'.$typeName.'/?_pn=' . ($page - 1) : '',
                           "self" => $this->request->rawUri,
                           'next' => $this->host . '/infoq/'.$typeName.'/?_pn=' . ($page + 1),
                       )
        );
        usleep(100000);
    }

    /**
     * 文章
     */
    public function article()
    {
        $this->showItems(__FUNCTION__);
    }

    /**
     * 演讲
     */
    public function presentation()
    {
        $this->showItems(__FUNCTION__);
    }

    public function arti2($artiId)
    {
        $arti = Db::get('ganhuo')->table('article')
            ->where(['article_id' => $artiId])
            ->getOne();
        if (!$arti) {
            throw new \Exception('hapn.u_notfound');
        }
        $this->request->outputFormat = 'html';
        $this->set('arti', $arti);

        $this->setView('infoq/arti.phtml');
    }

    /**
     * @throws Exception
     */
    public function arti()
    {
        $url = $this->get('url');
        if (!$url) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND);
        }
        if (strpos($url, $this->infoqHost) === false) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND);
        }

        $content = $this->loadContentFromUrl($url);
        $title = $this->getStr($content, array('property="og:title"', '"'), '"');

        $this->set('title', $title);
        $desc = $this->getStr($content, array('property="og:description"', '"'), '"');
        $this->set('desc', $desc);

        // 作者
        $author = trim($this->getStr($content, 'class="editorlink f_taxonomyEditor">', '</a>'));
        $this->set('author', $author);


        // 发布时间
        $pubtime = trim($this->getStr($content, '发布于', '日')) . '日';
        $this->set('pubtime', $pubtime);


        if (!preg_match(
            '#<div class="text_info(?: text_info_article)?">(.+?)<div class="random_links">#mus',
            $content,
            $ms
        )
        ) {
            throw new Exception(Exception::EXCEPTION_NOT_FOUND);
        }
        $content = $ms[1];
        if (($pos = strpos($content, '<hr />')) > 100) {
            $content = substr($content, 0, $pos);
        }
        if (($pos1 = strpos($content, 'class="related_sponsors visible stacked">')) !== false) {
            $pos2 = strpos($content, '<div class="clear"></div>', $pos1) + strlen('<div class="clear"></div>');
            $pos2 = strpos($content, '<div class="clear"></div>', $pos2);

            $pos2 = strpos($content, '</div>', $pos2) + 6;
            $content = substr($content, 0, $pos1) . substr($content, $pos2);
        }
        $content = preg_replace('#<([^>\s/]+)[^>]*>#', '<$1>', $content);
        $this->set('content', $content);

        $this->setView('tpl/arti.phtml');
        usleep(100000);
    }

}
