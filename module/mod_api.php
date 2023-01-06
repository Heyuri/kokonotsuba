<?php
class mod_api extends ModuleHelper {
	private $mypage;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = str_replace('&amp;', '&', $this->getModulePageURL());
	}

	public function getModuleName() {
		return __CLASS__.' : K! JSON API';
	}

	public function getModuleVersionInfo() {
		return 'Koko BBS Release 1';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();
		header('Content-Type: application/json');
		$no = intval($_GET['no']??'');

		if ($no) {
			$posts = $PIO->fetchPosts( $PIO->isThread($no) ? $PIO->fetchPostList($no) : array( $no ) );
		} else {
			$posts = $PIO->fetchPosts( $PIO->fetchThreadList() );
		}

		if(THREAD_PAGINATION){ // API caching
			$cacheETag = md5($no.'-'.count($posts).'-'.end($posts)['id']);
			$cacheFile = STORAGE_PATH.'cache/api-'.$no.'.';
			$cacheGzipPrefix = extension_loaded('zlib') ? 'compress.zlib://' : '';
			$cacheControl = 'cache';
			//$cacheControl = isset($_SERVER['HTTP_CACHE_CONTROL']) ? $_SERVER['HTTP_CACHE_CONTROL'] : ''; // respect user's cache wishes? (comment out to force caching)
			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == '"'.$cacheETag.'"'){
				header('HTTP/1.1 304 Not Modified');
				header('ETag: "'.$cacheETag.'"');
				return;
			}elseif(file_exists($cacheFile.$cacheETag) && $cacheControl != 'no-cache'){
				header('X-Cache: HIT');
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
				readfile($cacheGzipPrefix.$cacheFile.$cacheETag);
				return;
			}else{
				header('X-Cache: MISS');
			}
		}

		for ($i=0; $i<count($posts); $i++) {
			unset($posts[$i]['pwd']);
			unset($posts[$i]['host']);
		}

		$dat = json_encode($posts,  JSON_PRETTY_PRINT);
		echo $dat;

		if (THREAD_PAGINATION){ // API caching
			if ($oldCaches = glob($cacheFile.'*')){
				foreach($oldCaches as $o) unlink($o);
			}
			@$fp = fopen($cacheGzipPrefix.$cacheFile.$cacheETag, 'w');
			if($fp){
				fwrite($fp, $dat);
				fclose($fp);
				@chmod($cacheFile.$cacheETag, 0666);
				header('ETag: "'.$cacheETag.'"');
				header('Connection: close');
			}
		}
	}
}
