<?php
/**
 * Pixmicat! interface declarations
 *
 * @package PMCLibrary
 * @version $Id$
 */

/**
 * IPIO
 */
interface IPIO {

	public function fetchPostsFromThread($threadUID, $start = 0, $amount = 0);
	/**
	 * 取得 PIO 模組版本。
	 *
	 * @return string PIO 版本資訊字串
	 */
	public function pioVersion();


	/**
	 * 輸出文章清單
	 *
	 * @param  integer $resno  指定編號討論串
	 * @param  integer $start  起始位置
	 * @param  integer $amount 數目
	 * @return array          文章編號陣列
	 */
	public function fetchPostList($resno = 0, $start = 0, $amount = 0);


	/**
	 * 輸出文章
	 *
	 * @param  mixed $postlist 指定文章編號或文章編號陣列
	 * @param  string $fields   選擇輸出的欄位
	 * @return array           文章內容陣列
	 */
	public function fetchPosts($postlist, $fields = '*');

	/**
	 * 刪除舊附件 (輸出附件清單)
	 *
	 * @param  int  $total_size  目前使用容量
	 * @param  int  $storage_max 總容量限制
	 * @param  boolean $warnOnly    是否僅提醒不刪除
	 * @return array               附加圖檔及預覽圖陣列
	 */
	public function delOldAttachments($board, $total_size, $storage_max, $warnOnly = true);

	/**
	 * 刪除文章
	 *
	 * @param  array $posts 刪除之文章編號陣列
	 * @return array        附加圖檔及預覽圖陣列
	 */
	public function removePosts($posts);

	/**
	 * 刪除附件 (輸出附件清單)
	 *
	 * @param  array  $posts     刪除之文章編號陣列
	 * @param  boolean $recursion 是否遞迴尋找相關文章與回應
	 * @return array             附加圖檔及預覽圖陣列
	 */
	public function removeAttachments($posts, $recursion = false);

	/**
	 * 新增文章/討論串
	 *
	 * @param int $no        文章編號
	 * @param int  $resto     回應編號
	 * @param string  $md5chksum 附加圖MD5
	 * @param string  $category  類別
	 * @param string  $tim       時間戳
	 * @param string  $ext       附加圖副檔名
	 * @param int  $imgw      附加圖寬
	 * @param int  $imgh      附加圖高
	 * @param string  $imgsize   附加圖大小
	 * @param int  $tw        預覽圖寬
	 * @param int  $th        預覽圖高
	 * @param string  $pwd       密碼
	 * @param string  $now       發文時間字串
	 * @param string  $name      名稱
	 * @param string  $email     電子郵件
	 * @param string  $sub       標題
	 * @param string  $com       內文
	 * @param string  $host      主機名稱
	 * @param boolean $age       是否推文
	 * @param string  $status    狀態旗標
	 */
	public function addPost($boardUID, $no, $resto, $md5chksum, $category, $tim, $fname, $ext, $imgw, $imgh, 
		$imgsize, $tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, $age = false, $status = '');

	/**
	 * 檢查是否連續投稿
	 *
	 * @param  int  $lcount     檢查數目
	 * @param  string  $com        內文
	 * @param  int  $timestamp  發文時間戳
	 * @param  string  $pass       密碼
	 * @param  string  $passcookie Cookie 密碼
	 * @param  string  $host       主機名稱
	 * @param  boolean  $isupload   是否上傳附加圖檔
	 * @return boolean             是否為連續投稿
	 */
	public function isSuccessivePost($board, $lcount, $com, $timestamp, $pass,
		$passcookie, $host, $isupload);

	/**
	 * 檢查是否重複貼圖
	 *
	 * @param  int  $lcount     檢查數目
	 * @param  string  $md5hash MD5
	 * @return boolean          是否為連續貼圖
	 */
	public function isDuplicateAttachment($board, $lcount, $md5hash);

	/**
	 * 搜尋文章
	 *
	 * @param  array $keyword 關鍵字陣列
	 * @param  string $field   欄位
	 * @param  string $method  搜尋方法
	 * @return array          文章內容陣列
	 */
	public function searchPost($board, $keywords, $field, $method);

	/**
	 * 搜尋類別標籤
	 *
	 * @param  string $category 類別
	 * @return array           此類別之文章編號陣列
	 */
	public function searchCategory($category);

	/**
	 * 取得文章狀態
	 *
	 * @param  string $status 旗標狀態
	 * @return FlagHelper         旗標狀態修改物件
	 */
	public function getPostStatus($status);

	/**
	 * 更新文章
	 *
	 * @param int $no        文章編號
	 * @param array $newValues 新欄位值陣列
	 */
	public function updatePost($no, $newValues);

	/**
	 * 設定文章屬性
	 *
	 * @param int $no        文章編號
	 */
	public function setPostStatus($no, $newStatus);

	/**
	 * Get the OP attached to a post
	 *
	 * @param int $no            post number
	 */
	 public function getPostIP($no);
}

/**
 * IFileIO
 */
interface IFileIO {
    /**
     * 建置初始化。通常在安裝時做一次即可。
     */
    function init();

    /**
     * 圖檔是否存在。
     *
     * @param string $imgname 圖檔名稱
     * @return bool 是否存在
     */
    function imageExists($imgname, $board);

    /**
     * 刪除圖片。
     *
     * @param string $imgname 圖檔名稱
     */
    function deleteImage($imgname, $board);

    /**
     * 上傳圖片。
     *
     * @param string $imgname 圖檔名稱
     * @param string $imgpath 圖檔路徑
     * @param int $imgsize 圖檔檔案大小 (byte)
     */
    function uploadImage($imgname, $imgpath, $imgsize, $board);

    /**
     * 取得圖檔檔案大小。
     *
     * @param string $imgname 圖檔名稱
     * @return mixed 檔案大小 (byte) 或 0 (失敗時)
     */
    function getImageFilesize($imgname, $board);

    /**
     *　取得圖檔的 URL 以便 &lt;img&gt; 標籤顯示圖片。
     *
     * @param string $imgname 圖檔名稱
     * @return string 圖檔 URL
     */
    function getImageURL($imgname, $board);

    /**
     * 取得預覽圖檔名。
     *
     * @param string $thumbPattern 預覽圖檔名格式
     * @return string 預覽圖檔名
     */
    function resolveThumbName($thumbPattern, $board);

    /**
     * 回傳目前總檔案大小 (單位 KB)
     *
     * @return int 目前總檔案大小
     */
    function getCurrentStorageSize($board);

}

/**
 * IPIOCondition
 */
interface IPIOCondition {
	/**
	 * 檢查是否需要進行檢查步驟。
	 *
	 * @param  string $type  目前模式 ("predict" 預知提醒、"delete" 真正刪除)
	 * @param  mixed  $limit 判斷機制上限參數
	 * @return boolean       是否需要進行進一步檢查
	 */
	public static function check($board, $type, $limit);

	/**
	 * 列出需要刪除的文章編號列表。
	 *
	 * @param  string $type  目前模式 ("predict" 預知提醒、"delete" 真正刪除)
	 * @param  mixed  $limit 判斷機制上限參數
	 * @return array         文章編號列表陣列
	 */
	public static function listee($board, $type, $limit);

	/**
	 * 輸出 Condition 物件資訊。
	 *
	 * @param  mixed  $limit 判斷機制上限參數
	 * @return string        物件資訊文字
	 */
	public static function info($board, $limit);
}

/**
 * ILogger
 */
interface ILogger {
	/**
	 * 建構元。
	 *
	 * @param string $logName Logger 名稱
	 * @param string $logFile 記錄檔案位置
	 */
	public function __construct($logName, $logFile);

	/**
	 * 檢查是否 logger 要記錄 ERROR 等級。
	 *
	 * @return boolean 要記錄 ERROR 等級與否
	 */
	public function isErrorEnabled();


	/**
	 * 以 ERROR 等級記錄訊息。
	 *
	 * @param string $format 格式化訊息內容
	 * @param mixed $varargs 參數
	 */
	public function error($format, $varargs = '');
}

/**
 * MethodInterceptor (AOP Around Advice)
 */
interface MethodInterceptor {
	/**
	 * 代理呼叫方法。
	 *
	 * @param  array  $callable 要被呼叫的方法
	 * @param  array  $args     方法傳遞的參數
	 * @return mixed            方法執行的結果
	 */
	public function invoke(array $callable, array $args);
}

/**
 * IModule
 */
interface IModule {
	/**
	 * 回傳模組名稱方法
	 *
	 * @return string 模組名稱。建議回傳格式: mod_xxx : 簡短註解
	 */
	public function getModuleName();

	/**
	 * 回傳模組版本號方法
	 *
	 * @return string 模組版本號
	 */
	public function getModuleVersionInfo();
}

interface IBoard {
	/**
	 * Get the current board's configuration settings.
	 *
	 * @return array Configuration array
	 */
	public function loadBoardConfig(): bool|array;

	/**
	 * Get the template engine used by this board.
	 *
	 * @return templateEngine Template engine instance
	 */
	public function getBoardTemplateEngine(): templateEngine;

	/**
	 * Get the title of the board.
	 *
	 * @return string Board title
	 * 
	*/
	public function getBoardTitle(): string;

	/**
	 * Get the uid of the board.
	 *
	 * @return int Board uid
	 */
	public function getBoardUID(): int;

	/**
	 * Get the identifier of the board.
	 *
	 * @return string
	 */
	public function getBoardIdentifier(): string;

	/**
	 * Rebuild the html of the board.
	 *
	 * @return void
	 */
	public function rebuildBoard(): void;

	
}
