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
                '__LINK__' => $this->host . '/infoq/arti/'.$row['article_id'],
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

    public function arti($artiId)
    {
        $arti = Db::get('ganhuo')->table('article')
            ->where(['article_id' => $artiId])
            ->getOne();
        if (!$arti) {
            throw new \Exception('hapn.u_notfound');
        }
        $this->request->outputFormat = 'html';
        $content = $arti['content'];

        $content = preg_replace('#(\<img\s[^\>]*)(src=\")([^\"]+)(\"[^\/\>]*\/?\>)#i', '${1}data-src="${3}${4}', $content);
        $arti['content'] = $content;

        $this->set('arti', $arti);

        $this->setView('infoq/arti.phtml');
    }
}
