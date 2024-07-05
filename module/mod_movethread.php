<?php
/* move thread module
 * REQUIREMENTS: mod_multiboard enabled.
 * PIO used for home backend
 * private db functions are for sending data to other boards
 */
class mod_movethread extends ModuleHelper {
	private $mypage;
    //db
	private $username;
    private $password;
    private $dbname;
    private $tablename;
  
    private $con;
     
    
	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->mypage = $this->getModulePageURL();
	} 

	public function getModuleName() {
		return __CLASS__.' : Move Thread';
	}

	public function getModuleVersionInfo() {
		return 'Kokonotsuba';
	}
	
	public function autoHookAdminList(&$modfunc, $post, $isres) {
		$FileIO = PMCLibrary::getFileIOInstance();
		if (valid() != LEV_ADMIN) return;
		if (!$isres)$modfunc.= '[<a href="'.$this->mypage.'&no='.$post['no'].'" title="move thread">MT</a>]';
	}
	
	private function copyFile($sourceFile, $destinationFile) {
	    // Check if source file exists
	    if (!file_exists($sourceFile)) {
	        return false;
	    }
	    // Attempt to copy the file
	    if (!copy($sourceFile, $destinationFile)) {
	        echo "Failed to copy {$sourceFile}...\n";
	        return false;
	    } else {
	        echo "Copied {$sourceFile} to {$destinationFile}\n";
	        return true;
	    }
	}
	//get full url
	private function full_path() {
	    $s = &$_SERVER;
	    $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on') ? true:false;
	    $sp = strtolower($s['SERVER_PROTOCOL']);
	    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
	    $port = $s['SERVER_PORT'];
	    $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
	    $host = isset($s['HTTP_X_FORWARDED_HOST']) ? $s['HTTP_X_FORWARDED_HOST'] : (isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null);
	    $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
	    $uri = $protocol . '://' . $host . $s['REQUEST_URI'];
	    $segments = explode('?', $uri, 2);
	    $url = $segments[0];
	    return $url;
	}
	//draw list of boards from dat
	private function drawBoardList($brdDat) {
	    $dat = '<table><tbody><tr>
								<td class="postblock" align="left">
									<label for "board"><b>boards</b></label></td>
							';
	    $dat .= '<td>'; 
	    foreach($brdDat['boards'] as $key=>$board) {
	        if($board['tablename'] == $this->tablename && $board['dbname'] == $this->dbname) continue;
	        $dat .= '
                        <table>
                            <tr>
                                <td>
                                    <span><label><input type="radio" id="board" name="board" value="'.$key.'">'.$board['boardname'].'</label></span>
                                </td>
                            </tr>
                        </table>';
	    }
	    $dat .= '<td>';
	    $dat .= '</tr></tbody></table>';
	    return $dat;
	}
	private function mysqli_call($query, $errarray=false) {
	    $resource = $this->con->query($query);
	    if(is_array($errarray) && $resource===false) die($query);
	    else return $resource;
	}
	private  function getLastPostNo($board, $state){
	    $tree = $this->mysqli_call('SELECT AUTO_INCREMENT AS size FROM information_schema.tables WHERE table_name="'.$board['tablename'].'" AND table_schema="'.$board['dbname'].'"', array('Get the last No. failed', __LINE__));
	    $lastno = $tree->fetch_row();
	    $tree->free();
	    switch($state){
	        case 'beforeCommit':
	            return $lastno[0] - 1;
	        case 'afterCommit':
	            return $lastno[0];
	    }
	    return 0;
	}
	/* thread ageru */
	private function bumpThread($board,$tno,$future=false){    
	    $now = gmdate("Y-m-d H:i:s");
	    if ($future) {
	        $now = gmdate("Y-m-d H:i:s", strtotime("+5 seconds"));
	    }
	    
	    $SQL = "UPDATE ".$board['dbname'].'.'.$board['tablename']." SET root=? WHERE no=?";
	    $stmt = $this->con->prepare($SQL);
	    $stmt->bind_param("si",$now,$tno);
	    $stmt->execute();

	    return true;
	}
	private function copyAttachmentToBoardSrc($board, $post) {
	    $this->copyFile(IMG_DIR.$post['tim'].$post['ext'], $board['imgpath'].$post['tim'].$post['ext']); //copy file (if it exists)
	    $this->copyFile(IMG_DIR.$post['tim'].'s.'.THUMB_SETTING['Format'], $board['imgpath'].$post['tim'].'s'.$post['ext']); //copy file thumb (if it exists)
	}
	private function addPost($board, $no, $resto, $md5chksum, $category, $tim, $fname, $ext, $imgw, $imgh, $imgsize, $tw, $th, $pwd, $now, $name, $email, $sub, $com, $host, $age=false, $status='') {
	    $time = floor(substr($tim, 0, -3)); // 13位數的數字串是檔名，10位數的才是時間數值
	    $updatetime = gmdate('Y-m-d H:i:s'); // 更動時間 (UTC)
	    if($resto){ // 新增回應
	        $root = '2005-03-14 12:12:12';
	    }else $root = $updatetime; // 新增討論串, 討論串最後被更新時間
	 
	    $SQL = 'INSERT INTO '.$board['dbname'].'.'.$board['tablename'].' (resto,root,time,md5chksum,category,tim,fname,ext,imgw,imgh,imgsize,tw,th,pwd,now,name,email,sub,com,host,status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
	    
	    $stmt = $this->con->prepare($SQL);
	    if (!$stmt) {
	        die('Error in preparing statement: ' . $this->con->error);
	    } 
	    
	    $stmt->bind_param('isissdssiisiissssssss',
	        $resto,     //resto     Integer
	        $root,      //root      String
	        $time,      //time      Integer
	        $md5chksum, //md5chksum String
	        $category,  //category  String
	        $tim,       //tim       Double
	        $fname,     //fname     String
	        $ext,       //ext       String
	        $imgw,      //imgw      Integer
	        $imgh,      //imgh      Integer
	        $imgsize,   //imgsize   String
	        $tw,        //tw        Integer
	        $th,        //th        Integer
	        $pwd,       //pwd       String
	        $now,       //now       String
	        $name,      //name      String
	        $email,     //email     String
	        $sub,       //sub       String
	        $com,       //com       String
	        $host,      //host      String
	        $status     //status    String
	        );
	    
	    $stmt->execute();
	    if ($stmt->errno) {
	        die('Error executing statement: ' . $stmt->error);
	    }
	    
	    // if not auto saged then it will bump the parent thread
	    if ($age && $resto) {
	        $this->bumpThread($board, $resto);
	    }
	    
	    $stmt->close(); 
	}
	private function getCreds($connStr) {
	    if(preg_match('/^mysqli:\/\/(.*)\:(.*)\@(.*)(?:\:([0-9]+))?\/(.*)\/(.*)\/$/i', $connStr, $linkinfos)){
	        $this->username = $linkinfos[1];	// DB User
	        $this->password = $linkinfos[2];	// DB Pass
	        $this->dbname = $linkinfos[5];		// Database
	        $this->tablename = $linkinfos[6];	// Table name
	    }
	} 
	private function updateQuoteLink($threadData,  $oldPno, $newNo) {
	    foreach($threadData as &$postcoms) {
	        $postcoms['com'] = str_replace($oldPno, $newNo, $postcoms['com']);//skips if not found
	    }
	    return $threadData;
	}
	private function registNewThreadToBoard($threadDat, $board) {
	    $PIO = PMCLibrary::getPIOInstance();
	    //transfer data
	    $threadOP = $threadDat[0];
	    $newThreadNo = 0;
       
	    /* regist OP post */
	    $newNo = $this->getLastPostNo($board, 'beforeCommit') + 1;
	    $newThreadNo = $newNo; //if thread then set the new thread number
	    //change quote link
	    $threadDat = $this->updateQuoteLink($threadDat, $threadOP['no'], $newNo);

	    if($threadOP['ext'] != '') $this->copyAttachmentToBoardSrc($board, $threadOP);
	    $this->addPost($board, $newNo, 0, $threadOP['md5chksum'], $threadOP['category'], $threadOP['tim'], $threadOP['fname'], $threadOP['ext'], $threadOP['imgw'], $threadOP['imgh'], $threadOP['imgsize'], $threadOP['tw'], $threadOP['th'], 				$threadOP['pwd'], $threadOP['now'], $threadOP['name'], $threadOP['email'], $threadOP['sub'], $threadOP['com'], $threadOP['host'], $threadOP['age'], $threadOP['status']); //add OP
	    
	    
	    $threadDatModifiedCom = $threadDat; //foreach can't be modified while running
	    foreach($threadDat as $key=>$post) {
	       if($post['resto'] == 0) continue; // skip OP, it was already been processes
	       $newNo = $this->getLastPostNo($board, 'beforeCommit') + 1;

	       //change quote link (inception
	       $threadDatModifiedCom = $this->updateQuoteLink($threadDatModifiedCom, $post['no'] , $newNo);
	       if($post['ext'] != '') $this->copyAttachmentToBoardSrc($board, $post);
	       $this->addPost($board, $newNo, $newThreadNo, $post['md5chksum'], $post['category'], $post['tim'], $post['fname'], $post['ext'], $post['imgw'], $post['imgh'], $post['imgsize'], $post['tw'], $post['th'], $post['pwd'], $post['now'], $post['name'], $post['email'], $post['sub'], $threadDatModifiedCom[$key]['com'], $post['host'], $post['age'], $post['status']);
	    }
	}
	public function ModulePage() {
	    if (valid() < LEV_ADMIN) error('403 Access denied');
	    
		$PIO = PMCLibrary::getPIOInstance();
		$this->getCreds(CONNECTION_STRING); //get credentials & updatre member cred variables
		$boardData = json_decode(file_get_contents($this->full_path().'?'.'mode=module&load=mod_multiboard'), true);
		
		if ($_SERVER['REQUEST_METHOD']!='POST') { 
			$dat = '';
			head($dat);
			$dat .= '[<a href="'.PHP_SELF2.'?'.$_SERVER['REQUEST_TIME'].'">Return</a>]<br>
			<center><fieldset class="menu" style="display: inline-block;"><legend>Move Thread</legend>
				<form action="'.PHP_SELF.'" method="POST">
					<input type="hidden" name="mode" value="module" />
					<input type="hidden" name="load" value="mod_movethread" />
					<label>Post No. '.($_GET['no']??'0').'</label><br />
                    <input type="hidden" name="no" value="'.($_GET['no']??'0').'"/>
                    <br /> <label>Destination board</label>'.$this->drawBoardList($boardData).'<br />
					
                    <center><input type="submit" value="Move"></center>
			</form>
			</fieldset> </center>
			';
			foot($dat);
			echo $dat; 
		}
		else {
			$no = intval($_POST['no']);
			$post = $PIO->fetchPosts($no)[0]; if (!$post) error('ERROR: That thread does not exist.');
			if($post['resto'] != 0) error('That is not a thread.');
			$destination = $boardData['boards'][$_POST['board']]; //destination board key

			//exit if no board is selected
			if(!isset($destination)) error('No board selected.');

            if (!$this->con=mysqli_connect('127.0.0.1', $this->username, $this->password)) { //connection for destination database
                echo S_SQLCONF;	//unable to connect to DB (wrong user/pass?)
                error('Could not establish connection to destination board');
            }		
           
            $threadDat =  $PIO->fetchPosts($PIO->fetchPostList($post['no'])); //get posts in thread
            $this->registNewThreadToBoard($threadDat, $destination, $boardData); // copy thread to destination board 
            $this->con->commit(); //commit changes
            
			updatelog();
			echo "success!";
			redirect(PHP_SELF);
		}
	}
}
