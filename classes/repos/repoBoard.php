<?php
require_once  __DIR__ .'/DBConnection.php';
require_once  __DIR__ .'/interfaces.php';
require_once __DIR__ .'/../board.php';

class BoardRepoClass implements BoardRepositoryInterface {
    // this is a singleton.
    // these functions should be disabled. and getInstance should be used insted.
    private function __clone() {}
    public function __wakeup() { throw new Exception("Unserialization of AuthClass instances is not allowed.");}

    private $db;
    private static $instance = null;
    private function __construct() {
        $this->db = DatabaseConnection::getInstance(); 
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new BoardRepoClass();
        }
        return self::$instance;
    }
    public function updateBoard($board) {
        $query = "UPDATE boardTable SET configPath = '{$board->getConfPath()}' , lastPostID = {$board->getLastPostID()} WHERE boardID = {$board->getBoardID()}";
        $success = $this->db->query($query);
        return $success;
    }
    public function loadBoards() {
        $boards = [];
        $query = "SELECT * FROM boardTable";
        $result = $this->db->query($query);
    
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $boards[] = new boardClass($row['configPath'], $row['boardID']);
            }
        }
        return $boards;
    }
    public function loadBoardByID($boardID){
        $query = "SELECT * FROM boardTable WHERE boardID = $boardID";
        $result = $this->db->query($query);
    
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc(); 
            $board = new boardClass($row['configPath'],$row['boardID']);
            return $board;
        } else {
            return null;
        }
    }
    
    public function deleteBoardByID($boardID) {
        $query = "DELETE FROM boardTable WHERE boardID = $boardID";
        $success = $this->db->query($query);
        return $success;
    }

    public function createBoard($board) {
        $query = "INSERT INTO boardTable (configPath) VALUES ('{$board->getConf()}')";
        $success = $this->db->query($query);
        if ($success) {
            $board->setBoardID($this->db->insert_id);
        }
        return $success;
    }
}