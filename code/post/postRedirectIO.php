<?php

class postRedirectIO {
	private $boardTable, $threadTable, $redirectsTable; // Table name
	private $db; // Database connection
	private static $instance;

	public function __construct($dbSettings){
		$boardIO = boardIO::getInstance();
		
		$this->boardTable = $dbSettings['BOARD_TABLE']; 
		$this->threadTable = $dbSettings['THREAD_TABLE'];
		$this->redirectsTable = $dbSettings['THREAD_REDIRECT_TABLE'];
		
		$this->db = DatabaseConnection::getInstance($dbSettings); // Get the PDO instance;
	}
	
	public static function createInstance($dbSettings) {
		if (self::$instance === null) {
			$globalConfig = getGlobalConfig();
			self::$instance = new LoggerInjector(
				new self($dbSettings),
				new LoggerInterceptor(PMCLibrary::getLoggerInstance($globalConfig['ERROR_HANDLER_FILE'], 'postRedirectIO')));
		}
		return self::$instance;
	}
	
	public static function getInstance() {
		return self::$instance;
	}


    public function addNewRedirect($original_board_uid, $new_board_uid, $thread_uid) {
        if(intval($original_board_uid) === intval($new_board_uid)) return; //prevent redirect loop
        
        //delete any pre-existing redirects for the thread
        $deleteExistingRedirectsQuery = "DELETE FROM {$this->redirectsTable} WHERE thread_uid = :thread_uid";
        $this->db->execute($deleteExistingRedirectsQuery, [':thread_uid' => $thread_uid]);

        $query = "INSERT INTO {$this->redirectsTable} (original_board_uid, new_board_uid, thread_uid, post_op_number) VALUES(:original_board_uid, :new_board_uid, :thread_uid, (SELECT post_op_number FROM {$this->threadTable} WHERE thread_uid = :thread_uid))";
        $params = [
            ':original_board_uid' => intval($original_board_uid),
            ':new_board_uid' => intval($new_board_uid),
            ':thread_uid' => strval($thread_uid),
        ];

        $this->db->execute($query, $params);
    }

    public function getRedirectByID($id) {
        $query = "SELECT * FROM {$this->redirectsTable} WHERE redirect_id = :redirect_id";
        $redirect = $this->db->fetchAsClass($query, [':redirect_id' => $id], 'threadRedirect');

        return $redirect;
    }
    
    public function deleteRedirectByID($id) {
        $query = "DELETE FROM {$this->redirectsTable} WHERE redirect_id = :redirect_id";
        $this->db->execute($query, [':redirect_id' => $id]);
    }

    public function deleteRedirectByThreadUID($thread_uid) {
        $query = "DELETE FROM {$this->redirectsTable} WHERE thread_uid = :thread_uid";
        $this->db->execute($query, [':thread_uid' => $thread_uid]);
    }

    public function resolveRedirectedThreadLinkFromThreadUID($thread_uid) {
        $threadSingleton = threadSingleton::getInstance();
        $boardIO = boardIO::getInstance();

        $thread = $threadSingleton->getThreadByUID($thread_uid);

        $threadBoard = $boardIO->getBoardByUID($thread['boardUID']);
        $threadBoardConfig = $threadBoard->loadBoardConfig();
        $boardURL = $threadBoard->getBoardURL();
       
        $threadURL = $boardURL.$threadBoardConfig['PHP_SELF'].'?res='.$thread['post_op_number'];

        return $threadURL;
    }

    public function resolveRedirectedThreadLinkFromPostOpNumber($board, $resno) {
        $PIO = PIOPDO::getInstance();
        $boardIO = boardIO::getInstance();
        
        $query = "SELECT * FROM {$this->redirectsTable} WHERE original_board_uid = :board_uid AND post_op_number = :resno";
        $params = [
            ':board_uid' => $board->getBoardUID(),
            ':resno' => $resno,
        ];
        $redirect = $this->db->fetchAsClass($query, $params, 'threadRedirect');
        if(!$redirect) return; //no redirect found

        $newBoardFromRedirect = $boardIO->getBoardByUID($redirect->getNewBoardUID());
        $newBoardConfig = $newBoardFromRedirect->loadBoardConfig();
        $newBoardURL = $newBoardFromRedirect->getBoardURL();

        $thread_uid = $redirect->getThreadUID();
        $redirectThread = $thread = $PIO->getThreadByUID($thread_uid);

        $redirectNumber = $redirectThread['post_op_number'];

        $threadURL = $newBoardURL.$newBoardConfig['PHP_SELF'].'?res='.$redirectNumber;
        return $threadURL;
    }
}