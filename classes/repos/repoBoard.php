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
        $path = $board->getConfPath();
        $lastID = $board->getLastPostID();
        $id = $board->getBoardID();
        $stmt = $this->db->prepare("UPDATE boardTable SET configPath = ?, lastPostID = ? WHERE boardID = ?");
        $stmt->bind_param("sii", $path, $lastID, $id );
        $success = $stmt->execute();
        $stmt->close();
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
    public function loadBoardByID($boardID) {
        $stmt = $this->db->prepare("SELECT * FROM boardTable WHERE boardID = ?");
        $stmt->bind_param("i", $boardID);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($row = $result->fetch_assoc()) {
            $board = new boardClass($row['configPath'], $row['boardID']);
            $stmt->close();
            return $board;
        } else {
            $stmt->close();
            return null;
        }
    }
    
    public function deleteBoardByID($boardID) {
        $stmt = $this->db->prepare("DELETE FROM boardTable WHERE boardID = ?");
        $stmt->bind_param("i", $boardID);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    public function createBoard($board) {
        $conf = $board->getConfPath();
        $stmt = $this->db->prepare("INSERT INTO boardTable (configPath) VALUES (?)");
        $stmt->bind_param("s", $conf);
        $success = $stmt->execute();
        if ($success) {
            $board->setBoardID($this->db->insert_id);
        }
        $stmt->close();
        return $success;
    }  
}
