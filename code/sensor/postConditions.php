<?php
/**
 * PIO Condition Object
 *
 * 判斷文章是否符合刪除條件並列出刪除編號
 *
 * @package PMCLibrary
 * @version $Id$
 */

/* 以總文章篇數作為刪除判斷 */
class ByPostCountCondition implements IPIOCondition {
	public static function check($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		return $PIO->postCountFromBoard($board) >= $limit * ($type=='predict' ? 0.95 : 1);
	}

	public static function listee($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		return $PIO->fetchPostListFromBoard($board, 0,
			intval($limit * ($type=='predict' ? 0.95 : 1)) - 1, $limit);
	}

	public static function info($board, $limit){
		$PIO = PIOPDO::getInstance();
		return __CLASS__.': '.($pcnt=$PIO->postCountFromBoard($board)).'/'.$limit.
			sprintf(' (%.2f%%)',($pcnt/$limit*100));
	}
}

/* 以總討論串數作為刪除判斷 */
class ByThreadCountCondition implements IPIOCondition {
	public static function check($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		return $PIO->threadCountFromBoard($board) >= ($type=='predict' ? $limit * 0.95 : 1);
	}

	public static function listee($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		return $PIO->fetchThreadListFromBoard($board,
			intval(($limit - 1) * ($type=='predict' ? 0.95 : 1)), $limit, true);
	}

	public static function info($board, $limit){
		$PIO = PIOPDO::getInstance();
		return __CLASS__.': '.($tcnt=$PIO->threadCountFromBoard($board)).'/'.$limit.
			sprintf(' (%.2f%%)',($tcnt/$limit*100));
	}
}

/* 以討論串生存時間作為刪除判斷 */
class ByThreadAliveTimeCondition implements IPIOCondition {
	public static function check($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		// 最舊討論串編號
		$oldestThreadNo = $PIO::fetchThreadListFromBoard($board, $PIO->threadCountFromBoard($board) - 1, 1, true);
		$oldestThread = $PIO->fetchPosts($oldestThreadNo);
		return (time() - substr($oldestThread[0]['tim'], 0, 10) >= 86400 *
			$limit * ($type=='predict' ? 0.95 : 1));
	}

	public static function listee($board, $type, $limit){
		$PIO = PIOPDO::getInstance();
		// 討論串編號陣列 (由舊到新)
		$ThreadNo = $PIO->fetchThreadListFromBoard($board, 0, 0, true); sort($ThreadNo);
		$NowTime = time();
		$i = 0;
		foreach($ThreadNo as $t){
			$post = $PIO->fetchPosts($t);
			if($NowTime - substr($post[0]['tim'], 0, 10) < 86400 * $limit *
				($type=='predict' ? 0.95 : 1)) break; // 時間不符合
			$i++;
		}
		if(count($ThreadNo)===$i){ $i--; } // 保留最新的一篇避免全部刪除
		return array_slice($ThreadNo, 0, $i);
	}

	public static function info($board, $limit){
		return __CLASS__.": $limit day(s)";
	}
}
