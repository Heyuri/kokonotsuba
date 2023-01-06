<?php
class mod_rss extends ModuleHelper {
	// RSS 產生最大篇數
	private $FEED_COUNT = 10;
	// RSS 更新時機 (1: 瀏覽 MODULEPAGE 時更新, 2: 有新文章貼出時更新)
	private $FEED_UPDATETYPE = 1;
	// 資料取出形式 (T: 討論串取向, P: 文章取向)
	private $FEED_DISPLAYTYPE = 'T';
	// 資料狀態暫存檔 (檢查資料需不需要更新)
	private $FEED_STATUSFILE;
	// 資料輸出暫存檔 (靜態快取Feed格式)
	private $FEED_CACHEFILE = 'rss.xml';

	// 基底 URL
	private $BASEDIR;
	// RSS 連結
	private $SELF;

	public function __construct($PMS) {
		parent::__construct($PMS);

		$this->BASEDIR = fullURL();
		switch ($this->FEED_UPDATETYPE) {
			case 1: // MODULEPAGE
				$this->SELF = $this->BASEDIR.$this->getModulePageURL();
				$this->FEED_STATUSFILE = __CLASS__.'.tmp';
				break;
			case 2: // Update on RegistAfterCommit
				$this->SELF = $this->BASEDIR.$this->FEED_CACHEFILE;
				break;
		}
	}

	public function getModuleName() {
		return $this->moduleNameBuilder('提供 RSS Feed 訂閱服務');
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	/* 在頁面加入指向 RSS 的 <link> 標籤*/
	public function autoHookHead(&$txt, $isReply){
		$txt .= '<link rel="alternate" type="application/rss+xml" title="RSS 2.0 Feed" href="'.$this->SELF.'" />';
	}

	public function autoHookToplink(&$linkbar, $isReply){
		$linkbar .= ' [<a href="'.$this->SELF.'">RSS feed</a>] ';
	}

	/* 文章儲存後更新 RSS 檔案 ($this->FEED_UPDATETYPE == 2 觸發) */
	public function autoHookRegistAfterCommit(){
		if ($this->FEED_UPDATETYPE == 2) {
			$this->GenerateCache();
		}
	}

	public function ModulePage() {
		if ($this->IsDATAUpdated()) {
			// 若資料已更新則也更新RSS Feed快取
			$this->GenerateCache();
		}
		// 重導向到靜態快取
		$this->RedirectToCache();
	}

	/* 檢查資料有沒有更新 */
	private function IsDATAUpdated() {
		// 強迫更新RSS Feed
		if(isset($_GET['force'])) return true;

		$PIO = PMCLibrary::getPIOInstance();
		$lastNo = $PIO->getLastPostNo('afterCommit');
		// 讀取狀態暫存資料
		$lastNoCache = file_exists($this->FEED_STATUSFILE) ?
			file_get_contents($this->FEED_STATUSFILE) : 0;
		// LastNo 相同，沒有更新
		if($lastNo == $lastNoCache) return false;

		$fp = fopen($this->FEED_STATUSFILE, 'w');
		flock($fp, LOCK_EX);
		fwrite($fp, $lastNo);
		flock($fp, LOCK_UN);
		fclose($fp);
		@chmod($this->FEED_STATUSFILE, 0666);
		return true;
	}

	/* 生成 / 更新靜態快取RSS Feed檔案 */
	private function GenerateCache() {
		$PIO = PMCLibrary::getPIOInstance();
		$FileIO = PMCLibrary::getFileIOInstance();
		// RFC標準所用之時區格式
		$RFC_timezone = ' '.(TIME_ZONE < 0 ? '-' : '+').substr('0'.abs(TIME_ZONE), -2).'00';

		switch ($this->FEED_DISPLAYTYPE) {
			case 'T':
				// 取出前n筆討論串首篇編號
				$plist = $PIO->fetchThreadList(0, $this->FEED_COUNT);
				$plist_count = count($plist);
				// 為何這樣取？避免 SQL-like 自動排序喪失時間順序
				$post = array();
				for ($p = 0; $p < $plist_count; $p++) {
					// 取出編號文章資料
					$post[] = current($PIO->fetchPosts($plist[$p]));
				}
				break;
			case 'P':
				// 取出前n筆文章編號
				$plist = $PIO->fetchPostList(0, 0, $this->FEED_COUNT);
				$post = $PIO->fetchPosts($plist);
				break;
		}
		$post_count = count($post);
		// RSS Feed內容
		$tmp_c = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>'.TITLE.'</title>
<link>'.$this->BASEDIR.'</link>
<description>'.TITLE.'</description>
<language>'.PIXMICAT_LANGUAGE.'</language>
<generator>'.$this->getModuleName().' '.$this->getModuleVersionInfo().'</generator>
<atom:link href="'.$this->SELF.'" rel="self" type="application/rss+xml" />
';
		for ($i = 0; $i < $post_count; $i++) {
			$imglink = ''; // 圖檔
			$resto = 0; // 回應
			list($no, $resto, $time, $tw, $th, $tim, $ext, $sub, $com) = array(
				$post[$i]['no'],
				$post[$i]['resto'],
				substr($post[$i]['tim'], 0, -3),
				$post[$i]['tw'],
				$post[$i]['th'],
				$post[$i]['tim'],
				$post[$i]['ext'],
				$post[$i]['sub'],
				$post[$i]['com']
			);

			// 處理資料
			if ($ext && $FileIO->imageExists($tim.'s.jpg')) {
				$imglink = sprintf('<img src="%s" alt="%s" width="%d" height="%d" /><br />',
					$FileIO->getImageURL($tim.'s.jpg'),
					$tim.$ext,
					$tw,
					$th
				);
			}
			// 本地時間RFC標準格式
			$time = gmdate("D, d M Y H:i:s", $time + TIME_ZONE * 60 * 60).$RFC_timezone;
			$reslink = $this->BASEDIR.PHP_SELF.'?res='.($resto ? $resto : $no);
			switch ($this->FEED_DISPLAYTYPE) {
				case 'T':
					// 標題 No.編號 (Res:回應數)
					$titleBar = $sub.' No.'.$no.' (Res: '.($PIO->postCount($no) - 1).')';
					break;
				case 'P':
					// 標題 (編號)
					$titleBar = $sub.' ('.$no.')';
					break;
			}

			$tmp_c .= '<item>
	<title>'.$titleBar.'</title>
	<link>'.$reslink.'</link>
	<description>
	<![CDATA[
'.$imglink.$com.'
	]]>
	</description>
	<comments>'.$reslink.'</comments>
	<guid isPermaLink="true">'.$reslink.'#r'.$no.'</guid>
	<pubDate>'.$time.'</pubDate>
</item>
';
		}
		$tmp_c .= '</channel>
</rss>';
		$fp = fopen($this->FEED_CACHEFILE, 'w');
		flock($fp, LOCK_EX);
		fwrite($fp, $tmp_c);
		flock($fp, LOCK_UN);
		fclose($fp);
		@chmod($this->FEED_CACHEFILE, 0666);
	}

	/* 重導向到靜態快取 */
	private function RedirectToCache() {
		header('HTTP/1.1 302 Moved Temporarily');
		header('Location: '.$this->BASEDIR.$this->FEED_CACHEFILE);
	}
}