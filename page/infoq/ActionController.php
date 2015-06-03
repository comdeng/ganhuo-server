<?php

class Infoq_Controller extends PageController
{
	private $host;
	private $infoqHost;
	public function _before($func, $args)
	{
		parent::_before($func, $args);
		$this->host = 'http://'.$this->request->host;
		$this->infoqHost = 'http://www.infoq.com';
	}
	
	function loadContentFromUrl($url)
	{
		$md5 = md5($url);
		$path = TMP_ROOT.$md5;
		if (is_readable($path) && time() - filemtime($path) < 60 * 60) {
			$content = file_get_contents($path);
		} else {
			require_once LIB_ROOT.'curl/Curl.php';
			$curl = new Curl(array(
					CURLOPT_TIMEOUT => 20,
			));
			$ret = $curl->get($url);
			if ($ret->code != 200) {
				throw new Exception('infoq.u_code url:'.$url.'; code:'.$ret->code);
			}
			$content = $ret->content;
			file_put_contents($path, $content);
		}
		return $content;
	}
	
	function getStr(&$str, $starts, $end, $mb = false)
	{
		if ($mb) {
			$funcs = array('mb_strpos', 'mb_strlen', 'mb_substr');
		} else {
			$funcs = array('strpos', 'strlen', 'substr');
		}
		
		if (is_int($starts)) {
			$pos1 = $starts;
		} else if (is_string($starts)) {
			$pos1 = call_user_func($funcs[0], $str, $starts) + call_user_func($funcs[1], $starts);
		} else {
			$pos1 = 0;
			foreach($starts as $start) {
				$pos1 = call_user_func($funcs[0], $str, $start, $pos1) + call_user_func($funcs[1], $start); 
			}
		}
		$pos2 = call_user_func($funcs[0], $str, $end, $pos1);
		$ret = call_user_func($funcs[2], $str, $pos1, $pos2 - $pos1);
		$str = call_user_func($funcs[2], $str, $pos2 + strlen($end));
		return $ret;
	}
	
	function news_action()
	{
		if (!$this->get('_of')) {
			$this->request->of = 'json';
		}
		$page = intval($this->get('_pn', 0));
		
		if ($page < 0) {
			$page = 0;
		}
		if ($page == 0) {
			$url = $this->infoqHost.'/cn/news/';
		} else {
			$url = $this->infoqHost.'/cn/news/'.(15 * $page);
		}
		$content = $this->loadContentFromUrl($url);
		
		$items = array();
		preg_replace_callback('#<div class="news_type_block">(.+?)</div>#ms', function($ms) use(&$items) {
			$str = $ms[1];
			
			$item['url'] = $this->infoqHost.$this->getStr($str, '"', '"');
			$item['title'] = trim($this->getStr($str, '>', '</a>'));
			$item['author']['url'] = '/cn/author/'.$this->getStr($str, '="/cn/author/', '"');
			$item['author']['name'] = trim($this->getstr($str, '">', '<'));
			$item['author']['__LINK__'] = $this->host.'/infoq/author/?url='.rawurlencode($item['author']['url']);
				
			// 检查是否有译者
			$pos1 = strpos($str, '="/cn/author/');
			if ($pos1 !== false) {
				$item['translator']['url'] = $this->getStr($str, $pos1 + 2, '"'); 
				$item['translator']['name'] = trim($this->getStr($str, '">', '<'));
				$item['translator']['__LINK__'] = $this->host.'/infoq/translator/?url='.rawurlencode($item['translator']['url']);
			}
			// 发布时间
			$item['time'] = trim($this->getStr($str, '发布于&nbsp;', '日', true)).'日';
			
			
			// 评论数
			$pos1 = strpos($str, ' class="nr">');
			if ($pos1 !== false) {
				$item['reply'] = intval($this->getStr($str, $pos1 + 12, '</span>'));
			} else {
				$item['reply'] = 0;
			}
			$item['__LINK__'] = $this->host.'/infoq/arti?url='.rawurlencode($item['url']);
			// 获取文章内容
			$item['summary'] = trim($this->getStr($str, '<p>', '</p>'));
			$items[] = $item;
		}, $content);
		
		$this->set('items', $items);
		$this->set('__LINKS__', array(
			"prev" => $page > 1 ? $this->host.'/infoq/news/?_pn='.($page - 1) : '',
			"self" => $this->request->rawUri,
			'next' => $this->host.'/infoq/news/?_pn='.($page + 1).'&_of=json&_pretty=1',
		));
	}

	function article_action()
	{
		if (!$this->get('_of')) {
			$this->request->of = 'json';
		}
		$page = intval($this->get('_pn', 0));
		
		if ($page < 0) {
			$page = 0;
		}
		if ($page == 0) {
			$url = $this->infoqHost.'/cn/articles/';
		} else {
			$url = $this->infoqHost.'/cn/articles/'.(12 * $page);
		}
		$content = $this->loadContentFromUrl($url);
		
		$items = array();
		preg_replace_callback('#<div class="news_type(1|2)(?: full_screen)?">(.+?)<div class="clear">#ms', function($ms) use(&$items) {
			$type = $ms[1];
			$str = $ms[2];
			
			$item['url'] = $this->infoqHost.$this->getStr($str, '"', '"');
			$item['title'] = trim($this->getStr($str, '>', '</a>'));
			$item['author']['url'] = '/cn/author/'.$this->getStr($str, '="/cn/author/', '"');
			$item['author']['name'] = trim($this->getstr($str, '">', '<'));
			$item['author']['__LINK__'] = $this->host.'/infoq/author/?url='.rawurlencode($item['author']['url']);
			
			// 检查是否有译者
			$pos1 = strpos($str, '="/cn/author/');
			if ($pos1 !== false) {
				$item['translator']['url'] = $this->getStr($str, $pos1 + 2, '"');
				$item['translator']['name'] = trim($this->getStr($str, '">', '<'));
				$item['translator']['__LINK__'] = $this->host.'/infoq/translator/?url='.rawurlencode($item['translator']['url']);
			}
			// 发布时间
			$item['time'] = trim($this->getStr($str, '发布于&nbsp;', '日', true)).'日';
				
			
			// 评论数
			$posReply = strpos($str, ' class="nr">');
			
			$item['reply'] = 0;
			// 评论有可能在summary后面
			if ($posReply) {
				$posReply+=12;
				$pos2 = strpos($str, '</span>', $posReply);
				$item['reply'] = intval(substr($str, $posReply, $pos2 - $posReply));
			}
			
			$item['__LINK__'] = $this->host.'/infoq/arti?url='.rawurlencode($item['url']);
			
			// 检查是否有图
			
			$posImg = strpos($str, '<img ') + 5;
			$posP = strpos($str, '<p>') + 3;
			if ($posImg) {
				$pos1 = strpos($str, 'src="', $posImg) + 5;
				$pos2 = strpos($str, '"', $pos1);
				
				$item['cover'] = substr($str, $pos1, $pos2 - $pos1);
				if ($posImg < $posP) {
					$pos1 = $posP;
				} else {
					$pos1 = strpos($str, '</a>', $posP) + 4;
				}
			}
			
			$pos2 = strpos($str, '</p>');
			// 获取文章内容
			$item['summary'] = trim(substr($str, $pos1, $pos2 - $pos1));
			$items[] = $item;
		}, $content);
		
			$this->set('items', $items);
			$this->set('__LINKS__', array(
					"prev" => $page > 1 ? $this->host.'/infoq/article/?_pn='.($page - 1) : '',
					"self" => $this->request->rawUri,
					'next' => $this->host.'/infoq/article/?_pn='.($page + 1).'&_of=json&_pretty=1',
			));
	}
	
	/**
	 * 演讲
	 */
	function presentation_action()
	{
		if (!$this->get('_of')) {
			$this->request->of = 'json';
		}
		$page = intval($this->get('_pn', 0));
		
		if ($page < 0) {
			$page = 0;
		}
		if ($page == 0) {
			$url = $this->infoqHost.'/cn/presentations/';
		} else {
			$url = $this->infoqHost.'/cn/presentations/'.(12 * $page);
		}
		$content = $this->loadContentFromUrl($url);
		
		$items = array();
		preg_replace_callback('#<div class="news_type_video[^"]+">(.+?)</p>#ms', function($ms) use(&$items) {
			$str = $ms[1];
				
			$item['url'] = $this->infoqHost.$this->getStr($str, '"', '"');
			$item['title'] = trim($this->getStr($str, 'title="', '"'));
			$item['cover'] = $this->getStr($str, 'src="', '"');
			$item['length'] = $this->getStr($str, 'class="videolength">', '</span>');
			
			$pos1 = strpos($str, 'class="author">') + strlen('class="author">');
			$pose = strpos($str, 'href="', $pos1) + 6;
			
			$item['author']['url'] = '/cn/author/'.$this->getStr($str, $pos1, '"');
			$item['author']['name'] = trim($this->getstr($str, '">', '<'));
			$item['author']['__LINK__'] = $this->host.'/infoq/author/?url='.rawurlencode($item['author']['url']);
				
			// 发布时间
			$item['time'] = trim($this->getStr($str, '发布于&nbsp;', '日', true)).'日';
		
				
			$item['__LINK__'] = $this->host.'/infoq/arti?url='.rawurlencode($item['url']);
				
			// 获取文章内容
			$pos1 = strpos($str, '<p>') + 3;
			$item['summary'] = trim(substr($str, $pos1));
			$items[] = $item;
		}, $content);
		
			$this->set('items', $items);
			$this->set('__LINKS__', array(
					"prev" => $page > 1 ? $this->host.'/infoq/presentation/?_pn='.($page - 1) : '',
					"self" => $this->request->rawUri,
					'next' => $this->host.'/infoq/presentation/?_pn='.($page + 1).'&_of=json&_pretty=1',
			));
	}
	
	function arti_action()
	{
		$url = $this->get('url');
		if (!$url) {
			throw new Exception('hapn.u_notfound');
		}
		if (strpos($url, $this->infoqHost) === false) {
			throw new Exception('hapn.u_notfound');
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
		$pubtime = trim($this->getStr($content, '发布于', '日')).'日';
		$this->set('pubtime', $pubtime);
		
		
		if (!preg_match('#<div class="text_info(?: text_info_article)?">(.+?)<div class="random_links">#mus', $content, $ms)) {
			throw new Exception('hapn.u_notfound');
		}
		$content = $ms[1];
		if ( ($pos = strpos($content, '<hr />')) > 100) {
			$content = substr($content, 0, $pos);
		}
		if ( ($pos1 = strpos($content, 'class="related_sponsors visible stacked">')) !== false) {
			$pos2 = strpos($content, '<div class="clear"></div>', $pos1) + strlen('<div class="clear"></div>');
			$pos2 = strpos($content, '<div class="clear"></div>', $pos2);
			
			$pos2 = strpos($content, '</div>', $pos2) + 6;
			$content = substr($content, 0, $pos1).substr($content, $pos2);
		}

		$content = preg_replace('#<([^>\s/]+)[^>]*>#','<$1>',$content);
		$this->set('content', $content);
		
		$this->setView('tpl/arti.phtml');
	}
	
	
}
