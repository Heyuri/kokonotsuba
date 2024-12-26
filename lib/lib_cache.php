<?php
//caching functions


/* Delete the old page cache file */
function deleteCache($no){
/*	foreach($no as $n){
		if($oldCaches = glob('./cache/'.$n.'-*')){
			foreach($oldCaches as $o) @unlink($o);
		}
	}*/
}

/* delete direct cache */
function unlinkCache($oldCaches) {
	//foreach($oldCaches as $o) unlink($o); // Clear old API caches
}

/* create html cache*/
function createHtmlCache($fp, $dat, $cacheETag) {
	fwrite($fp, $dat);
	fclose($fp);
	@chmod($cacheFile.$cacheETag, 0666);
	header('ETag: "'.$cacheETag.'"');
	header('Connection: close');
}
